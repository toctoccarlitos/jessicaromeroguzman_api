<?php
namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Doctrine\ORM\EntityManager;

class AuthService
{
    private EntityManager $em;

    public function __construct(?EntityManager $em = null)
    {
        $this->em = $em ?? app()->em;
    }

    public function authenticate(string $email, string $password): ?string
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user || !$user->verifyPassword($password)) {
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        return $this->generateJWT($user);
    }

    private function generateJWT(User $user): string
    {
        $payload = [
            'uid' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'exp' => time() + (int)$_ENV['JWT_EXPIRATION']
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }
}