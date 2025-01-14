<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\Newsletter;
use App\Service\EmailService;
use Doctrine\ORM\EntityManager;

class NewsletterController extends BaseController
{
    private EntityManager $em;
    private EmailService $emailService;
    private string $unsubscribeBaseUrl;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->emailService = new EmailService();
        $this->unsubscribeBaseUrl = $_ENV['APP_URL'] . '/api/newsletter/unsubscribe';
    }

    public function subscribe(Request $request): string
    {
        try {
            // Validación de seguridad
            $validation = security()->validateRequest($request);
            if (!$validation['valid']) {
                logger()->warning('Newsletter security validation failed', [
                    'reason' => $validation['message']
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => $validation['message']
                ], 400);
            }

            $data = $request->getBody();

            if (!isset($data['email'])) {
                logger()->warning('Newsletter subscription failed - missing email');
                return $this->json([
                    'status' => 'error',
                    'message' => 'Email es requerido'
                ], 400);
            }

            // Verificar reCAPTCHA
            if (!isset($data['recaptcha_token'])) {
                logger()->warning('Newsletter subscription failed - missing recaptcha token');
                return $this->json([
                    'status' => 'error',
                    'message' => 'Verificación de seguridad requerida'
                ], 400);
            }

            // Sanitizar y validar contenido
            $sanitizedData = security()->sanitizeAndValidate($data);
            if (!$sanitizedData['valid']) {
                return $this->json([
                    'status' => 'error',
                    'message' => $sanitizedData['message']
                ], 400);
            }

            if (!filter_var($sanitizedData['data']['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Email inválido'
                ], 400);
            }

            $newsletterFlag = false;

            try {
                $newsletter = $this->em->getRepository(Newsletter::class)
                    ->findOneBy(['email' => $sanitizedData['data']['email']]);

                if ($newsletter) {
                    if ($newsletter->getStatus() === 'active') {
                        $newsletterFlag = true;
                    } else {
                        $newsletter->subscribe();
                    }
                } else {
                    $newsletter = new Newsletter();
                    $newsletter->setEmail($sanitizedData['data']['email']);
                }

                if (!$newsletterFlag) {
                    $this->em->persist($newsletter);
                    $this->em->flush();

                    // Enviar emails de confirmación
                    $this->emailService->sendNewsletterConfirmation($newsletter);
                    $this->emailService->sendNewsletterNotification($newsletter);
                }

                return $this->json([
                    'status' => 'success',
                    'message' => 'Suscripción exitosa'
                ]);

            } catch (\Exception $e) {
                logger()->error('Error subscribing to newsletter', [
                    'email' => $sanitizedData['data']['email'],
                    'error' => $e->getMessage()
                ]);

                return $this->json([
                    'status' => 'error',
                    'message' => 'Error al procesar la suscripción'
                ], 500);
            }

        } catch (\Exception $e) {
            logger()->error('Error in newsletter subscription', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud'
            ], 500);
        }
    }

    public function unsubscribe(Request $request): string
    {
        $token = $request->getQuery('token');

        if (!$token) {
            return $this->json([
                'status' => 'error',
                'message' => 'Token es requerido'
            ], 400);
        }

        try {
            // Decodificar el token (email encriptado)
            $email = $this->decryptToken($token);

            $newsletter = $this->em->getRepository(Newsletter::class)
                ->findOneBy(['email' => $email]);

            if (!$newsletter) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Suscripción no encontrada'
                ], 404);
            }

            $newsletter->unsubscribe();
            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Te has dado de baja exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error unsubscribing from newsletter', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al procesar la baja'
            ], 500);
        }
    }

    private function decryptToken(string $token): string
    {
        // Desencriptar usando la clave secreta de la aplicación
        return openssl_decrypt(
            base64_decode($token),
            'AES-256-CBC',
            $_ENV['APP_SECRET'],
            0,
            substr($_ENV['APP_SECRET'], 0, 16)
        );
    }

    public function listSubscribers(Request $request): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 403);
        }

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 10));
        $sort = $request->getQuery('sort', 'createdAt');
        $order = $request->getQuery('order', 'DESC');
        $search = $request->getQuery('search', '');
        $status = $request->getQuery('status', '');
        $lastCheck = $request->getQuery('lastCheck');

        try {
            $qb = $this->em->createQueryBuilder();
            $qb->select('n')
            ->from(Newsletter::class, 'n');

            if ($search) {
                $qb->andWhere('n.email LIKE :search')
                ->setParameter('search', "%$search%");
            }

            if ($status) {
                $qb->andWhere('n.status = :status')
                ->setParameter('status', $status);
            }

            // Para polling: obtener solo suscriptores nuevos desde última verificación
            if ($lastCheck) {
                $lastCheckDate = new \DateTime($lastCheck);
                $qb->andWhere('n.createdAt > :lastCheck')
                ->setParameter('lastCheck', $lastCheckDate);
            }

            // Asegurar que el campo de ordenamiento sea válido
            $validSortFields = ['email', 'status', 'createdAt', 'subscribedAt', 'unsubscribedAt'];
            if (!in_array($sort, $validSortFields)) {
                $sort = 'createdAt';
            }

            $qb->orderBy("n.$sort", $order);

            // Contar total para paginación
            $countQb = clone $qb;
            $total = count($countQb->getQuery()->getResult());

            // Agregar paginación
            $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

            $subscribers = $qb->getQuery()->getResult();

            $data = [
                'items' => array_map(function($subscriber) {
                    return [
                        'id' => $subscriber->getId(),
                        'email' => $subscriber->getEmail(),
                        'status' => $subscriber->getStatus(),
                        'createdAt' => $subscriber->getCreatedAt()->format('Y-m-d H:i:s'),
                        'subscribedAt' => $subscriber->getSubscribedAt()->format('Y-m-d H:i:s'),
                        'unsubscribedAt' => $subscriber->getUnsubscribedAt() ? 
                            $subscriber->getUnsubscribedAt()->format('Y-m-d H:i:s') : null
                    ];
                }, $subscribers),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ],
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ];

            return $this->json([
                'status' => 'success',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            logger()->error('Error fetching newsletter subscribers', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al obtener los suscriptores'
            ], 500);
        }
    }

    public function updateSubscriberStatus(Request $request, int $id): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 403);
        }

        $data = $request->getBody();
        if (!isset($data['status'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Status es requerido'
            ], 400);
        }

        try {
            $subscriber = $this->em->getRepository(Newsletter::class)->find($id);
            if (!$subscriber) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Suscriptor no encontrado'
                ], 404);
            }

            // Actualizar estado
            if ($data['status'] === 'active') {
                $subscriber->subscribe();
            } else if ($data['status'] === 'unsubscribed') {
                $subscriber->unsubscribe();
            } else {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Estado inválido'
                ], 400);
            }

            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Estado actualizado exitosamente',
                'data' => [
                    'id' => $subscriber->getId(),
                    'email' => $subscriber->getEmail(),
                    'status' => $subscriber->getStatus(),
                    'subscribedAt' => $subscriber->getSubscribedAt()->format('Y-m-d H:i:s'),
                    'unsubscribedAt' => $subscriber->getUnsubscribedAt() ? 
                        $subscriber->getUnsubscribedAt()->format('Y-m-d H:i:s') : null
                ]
            ]);

        } catch (\Exception $e) {
            logger()->error('Error updating subscriber status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estado'
            ], 500);
        }
    }
}