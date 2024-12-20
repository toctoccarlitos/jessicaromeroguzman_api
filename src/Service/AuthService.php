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
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user || !$user->verifyPassword($password)) {
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        // Actualizar último login
        $user->setLastLoginAt(new \DateTime());
        $user->setLastLoginIp($_SERVER['REMOTE_ADDR'] ?? null);

        // Registrar actividad
        $this->activityService->logActivity(
            $user,
            ActivityLog::TYPE_LOGIN,
            'Usuario ha iniciado sesión',
            [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );

        $this->em->flush();

        return $user;
    }
}