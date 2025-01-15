<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManager;

class AuthService
{
    private EntityManager $em;
    private ActivityService $activityService;

    public function __construct()
    {
        $this->em = app()->em;
        $this->activityService = new ActivityService($this->em);
    }

    public function authenticate(string $email, string $password): ?User
    {
        try {
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if (!$user || !$user->verifyPassword($password)) {
                // Registrar intento fallido sin usuario
                $this->activityService->logActivity(
                    null,
                    ActivityLog::TYPE_LOGIN_FAILED,
                    'Intento de inicio de sesión fallido',
                    ['email' => $email]
                );
                return null;
            }

            if (!$user->isActive()) {
                $this->activityService->logActivity(
                    null,
                    ActivityLog::TYPE_LOGIN_INACTIVE,
                    'Intento de inicio de sesión con cuenta inactiva',
                    ['email' => $email]
                );
                return null;
            }

            // Actualizar último login
            $user->setLastLoginAt(new \DateTime());
            $user->setLastLoginIp($_SERVER['REMOTE_ADDR'] ?? null);

            // Registrar login exitoso con usuario
            $this->activityService->logActivity(
                $user,
                ActivityLog::TYPE_LOGIN,
                'Usuario ha iniciado sesión'
            );

            $this->em->flush();
            return $user;

        } catch (\Exception $e) {
            // Registrar error inesperado
            $this->activityService->logActivity(
                null,
                ActivityLog::TYPE_ERROR,
                'Error durante la autenticación: ' . $e->getMessage()
            );
            throw $e;
        }
    }
}