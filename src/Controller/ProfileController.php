<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Entity\ActivityLog;
use App\Service\ActivityService;
use Doctrine\ORM\EntityManager;
use App\Service\Logger\AppLogger;

class ProfileController extends BaseController
{
    private EntityManager $em;
    private AppLogger $logger;
    private ActivityService $activityService;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->logger = new AppLogger();
        $this->activityService = new ActivityService($em);
    }

    public function getProfile(Request $request): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user) {
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
            $this->logger->error('Error obteniendo perfil', [
                'user_id' => $request->getUserId()
            ], $e);

            return $this->json([
                'status' => 'error',
                'message' => 'Error al obtener el perfil'
            ], 500);
        }
    }

    public function changePassword(Request $request): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $data = $request->getBody();

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Contraseña actual y nueva son requeridas'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user || !$user->verifyPassword($data['current_password'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Contraseña actual incorrecta'
                ], 400);
            }

            // Validaciones de contraseña...

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

            $this->logger->info('Contraseña cambiada exitosamente', [
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error cambiando contraseña', [], $e);
            return $this->json([
                'status' => 'error',
                'message' => 'Error al cambiar la contraseña'
            ], 500);
        }
    }
}