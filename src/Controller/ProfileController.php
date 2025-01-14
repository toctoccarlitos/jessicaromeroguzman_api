<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Entity\ActivityLog;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManager;

class ProfileController extends BaseController
{
    private EntityManager $em;
    private ActivityService $activityService;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->activityService = new ActivityService($em);
    }

    public function getProfile(Request $request): string
    {
        if (!$request->getUser()) {
            logger()->warning("Unauthorized profile access attempt");
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user) {
                logger()->warning("Profile not found", [
                    'user_id' => $request->getUserId()
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Registrar la actividad de vista del perfil
            $this->activityService->logActivity(
                $user,
                ActivityLog::TYPE_PROFILE_VIEW,
                'Usuario ha consultado su perfil'
            );

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus(),
                    'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'lastLoginAt' => $user->getLastLoginAt() ?
                        $user->getLastLoginAt()->format('Y-m-d H:i:s') : null,
                    'lastLoginIp' => $user->getLastLoginIp()
                ]
            ]);

        } catch (\Exception $e) {
            logger()->error('Error retrieving profile', [
                'user_id' => $request->getUserId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al obtener el perfil'
            ], 500);
        }
    }

    public function changePassword(Request $request): string
    {
        if (!$request->getUser()) {
            logger()->warning('Unauthorized password change attempt');
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $data = $request->getBody();

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            logger()->warning('Password change attempt without required fields', [
                'user_id' => $request->getUserId()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Contraseña actual y nueva son requeridas'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user || !$user->verifyPassword($data['current_password'])) {
                logger()->warning('Invalid current password in change attempt', [
                    'user_id' => $request->getUserId()
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Contraseña actual incorrecta'
                ], 400);
            }

            // Validaciones de contraseña
            if (strlen($data['new_password']) < 8) {
                logger()->warning('Invalid new password length', [
                    'user_id' => $user->getId(),
                    'password_length' => strlen($data['new_password'])
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'La nueva contraseña debe tener al menos 8 caracteres'
                ], 400);
            }

            // Actualizar contraseña
            $user->setPassword($data['new_password']);
            $this->em->flush();

            // Registrar la actividad de cambio de contraseña
            $this->activityService->logActivity(
                $user,
                ActivityLog::TYPE_PASSWORD_CHANGE,
                'Usuario ha cambiado su contraseña',
                [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            );

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error changing password', [
                'user_id' => $request->getUserId(),
                'error' => $e->getMessage()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Error al cambiar la contraseña'
            ], 500);
        }
    }
}