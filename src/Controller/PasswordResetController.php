<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Entity\ActivityLog;
use App\Entity\PasswordResetToken;
use App\Service\EmailService;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManager;

class PasswordResetController extends BaseController
{
    private EntityManager $em;
    private EmailService $emailService;
    private ActivityService $activityService;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->emailService = new EmailService();
        $this->activityService = new ActivityService($em);
    }

    public function requestReset(Request $request): string
    {
        $data = $request->getBody();

        if (!isset($data['email'])) {
            logger()->warning('Password reset request without email');
            return $this->json([
                'status' => 'error',
                'message' => 'Email es requerido'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $data['email']]);

            if ($user) {
                logger()->info('Creating password reset token', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);

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
            } else {
                logger()->info('Password reset requested for non-existent email', [
                    'email' => $data['email']
                ]);
            }

            // Siempre devolver éxito para no revelar si el email existe
            return $this->json([
                'status' => 'success',
                'message' => 'Si el email existe, recibirás instrucciones para restablecer tu contraseña'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error processing password reset request', [
                'email' => $data['email'],
                'error' => $e->getMessage()
            ]);

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
            logger()->warning('Password reset attempt without required fields', [
                'has_token' => isset($data['token']),
                'has_password' => isset($data['password'])
            ]);
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

            if (!$token) {
                logger()->warning('Password reset attempt with invalid token', [
                    'token' => $data['token']
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Token inválido o expirado'
                ], 400);
            }

            if (!$token->isValid()) {
                logger()->warning('Password reset attempt with expired token', [
                    'token' => $data['token'],
                    'expired_at' => $token->getExpiresAt()->format('Y-m-d H:i:s')
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Token inválido o expirado'
                ], 400);
            }

            $user = $token->getUser();

            // Validar nueva contraseña
            if (strlen($data['password']) < 8) {
                logger()->warning('Password reset attempt with invalid password length', [
                    'user_id' => $user->getId(),
                    'password_length' => strlen($data['password'])
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'La contraseña debe tener al menos 8 caracteres'
                ], 400);
            }

            logger()->info('Resetting user password', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

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

            logger()->info('Password reset successful', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña restablecida exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error during password reset', [
                'token' => $data['token'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al restablecer la contraseña'
            ], 500);
        }
    }
}