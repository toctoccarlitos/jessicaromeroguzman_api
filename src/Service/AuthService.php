<?php
namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManager;

class AuthService
{
    private EntityManager $em;

    public function __construct()
    {
        $this->em = app()->em;
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

        return $user;
    }
}