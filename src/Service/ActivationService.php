<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivationToken;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\EmailService;

class ActivationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailService $emailService
    ) {}

    public function createActivationToken(User $user): ActivationToken
    {
        // Revocar tokens anteriores
        $existingTokens = $this->em->getRepository(ActivationToken::class)
            ->findBy(['user' => $user, 'used' => false]);

        foreach ($existingTokens as $token) {
            $token->setUsed(true);
        }

        // Crear nuevo token
        $token = new ActivationToken($user);
        $this->em->persist($token);
        $this->em->flush();

        // Enviar email
        $this->emailService->sendActivationEmail($user, $token);

        return $token;
    }

    public function activateUser(string $token, string $password): bool
    {
        $tokenEntity = $this->em->getRepository(ActivationToken::class)
            ->findOneBy(['token' => $token, 'used' => false]);

        if (!$tokenEntity || !$tokenEntity->isValid()) {
            return false;
        }

        $user = $tokenEntity->getUser();
        $user->setPassword($password);
        $user->setStatus(User::STATUS_ACTIVE);
        $user->setHasSetPassword(true);
        $user->setEmailVerifiedAt(new \DateTime());

        $tokenEntity->setUsed(true);

        $this->em->flush();
        return true;
    }

    public function resendActivation(User $user): ?ActivationToken
    {
        if ($user->hasSetPassword() || $user->getStatus() === User::STATUS_ACTIVE) {
            return null;
        }

        return $this->createActivationToken($user);
    }
}