<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Entity\ActivityLog;
use App\Entity\PasswordResetToken;
use App\Service\EmailService;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManager;
use App\Service\Logger\AppLogger;

class PasswordResetController extends BaseController
{
    private EntityManager $em;
    private EmailService $emailService;
    private AppLogger $logger;
    private ActivityService $activityService;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->emailService = new EmailService();
        $this->logger = new AppLogger();
        $this->activityService = new ActivityService($em);
    }

    public function requestReset(Request $request): string
    {
        $data = $request->getBody();

        if (!isset($data['email'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email es requerido'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $data['email']]);

            if ($user) {
                // Crear token
                $token = new PasswordResetToken($user);
                $this->em->persist($token);
                
                // Registrar la actividad
                $this->activityService->logActivity(
                    $user,
                    ActivityLog::TYPE_PASSWORD_RESET_REQUEST,
                    'Usuario ha solicitado restablecer su contraseña',
                    [
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]
                );

                $this->em->flush();
                
                // Enviar email
                $this->emailService->sendPasswordResetEmail($user, $token);
            }

            // Siempre devolver éxito para no revelar si el email existe
            return $this->json([
                'status' => 'success',
                'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en solicitud de reset', [
                'email' => $data['email']
            ], $e);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al procesar la solicitud'
            ], 500);
        }
    }

    public function resetPassword(Request $request): string
    {
        $data = $request->getBody();

        if (!isset($data['token']) || !isset($data['password'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Token y nueva contraseña son requeridos'
            ], 400);
        }

        try {
            $token = $this->em->getRepository(PasswordResetToken::class)
                ->findOneBy([
                    'token' => $data['token'],
                    'used' => false
                ]);

            if (!$token || !$token->isValid()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Token inválido o expirado'
                ], 400);
            }

            $user = $token->getUser();

            // Validar nueva contraseña
            if (strlen($data['password']) < 8) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'La contraseña debe tener al menos 8 caracteres'
                ], 400);
            }

            // Actualizar contraseña
            $user->setPassword($data['password']);
            $token->setUsed(true);

            // Registrar la actividad
            $this->activityService->logActivity(
                $user,
                ActivityLog::TYPE_PASSWORD_RESET,
                'Usuario ha restablecido su contraseña',
                [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            );

            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña restablecida exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error reseteando contraseña', [], $e);
            return $this->json([
                'status' => 'error',
                'message' => 'Error al restablecer la contraseña'
            ], 500);
        }
    }
}