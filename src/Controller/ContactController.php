<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\ContactMessage;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;

class ContactController extends BaseController
{
    private EmailService $emailService;
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->emailService = new EmailService();
    }

    public function submit(Request $request): string
    {
        $data = $request->getBody();

        if (!isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Nombre, email y mensaje son requeridos'
            ], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email inválido'
            ], 400);
        }

        try {
            $message = new ContactMessage();
            $message->setName($data['name'])
                   ->setEmail($data['email'])
                   ->setMessage($data['message']);

            if (isset($data['phone'])) {
                $message->setPhone($data['phone']);
            }

            $this->em->persist($message);
            $this->em->flush();

            // Enviar emails
            $this->emailService->sendContactConfirmation($message);
            $this->emailService->sendContactNotification($message);

            return $this->json([
                'status' => 'success',
                'message' => 'Mensaje enviado exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error submitting contact message', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al enviar el mensaje'
            ], 500);
        }
    }

    public function list(Request $request): string
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
            $qb->select('m')
               ->from(ContactMessage::class, 'm');

            if ($search) {
                $qb->andWhere('m.name LIKE :search OR m.email LIKE :search OR m.message LIKE :search')
                   ->setParameter('search', "%$search%");
            }

            if ($status) {
                $qb->andWhere('m.status = :status')
                   ->setParameter('status', $status);
            }

            if ($lastCheck) {
                $qb->andWhere('m.createdAt > :lastCheck')
                   ->setParameter('lastCheck', new \DateTime($lastCheck));
            }

            $qb->orderBy("m.$sort", $order);

            // Total count for pagination
            $countQb = clone $qb;
            $total = count($countQb->getQuery()->getResult());

            // Add pagination
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);

            $messages = $qb->getQuery()->getResult();

            $data = [
                'items' => array_map(function($message) {
                    return [
                        'id' => $message->getId(),
                        'name' => $message->getName(),
                        'email' => $message->getEmail(),
                        'phone' => $message->getPhone(),
                        'message' => $message->getMessage(),
                        'status' => $message->getStatus(),
                        'isRead' => $message->isRead(),
                        'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s')
                    ];
                }, $messages),
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
            logger()->error('Error fetching contact messages', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al obtener los mensajes'
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id): string
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
            $message = $this->em->getRepository(ContactMessage::class)->find($id);
            if (!$message) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Mensaje no encontrado'
                ], 404);
            }

            $message->setStatus($data['status']);
            if (isset($data['isRead'])) {
                $message->setIsRead($data['isRead']);
            }

            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Estado actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error updating message status', [
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estado'
            ], 500);
        }
    }
}