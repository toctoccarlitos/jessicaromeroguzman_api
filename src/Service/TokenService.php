<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Entity\BlacklistedToken;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function createTokenPair(User $user): array
    {
        $accessToken = $this->createAccessToken($user);

        $refreshToken = new RefreshToken($user);
        $this->em->persist($refreshToken);
        $this->em->flush();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken->getToken(),
            'expires_in' => $_ENV['JWT_EXPIRATION']
        ];
    }

    public function refreshTokens(string $refreshToken): ?array
    {
        $refreshTokenEntity = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $refreshToken]);

        if (!$refreshTokenEntity || !$refreshTokenEntity->isValid()) {
            return null;
        }

        $refreshTokenEntity->revoke();
        $this->em->flush();

        $user = $refreshTokenEntity->getUser();
        return $this->createTokenPair($user);
    }

    public function revokeRefreshToken(string $token): void
    {
        $refreshToken = $this->em->getRepository(RefreshToken::class)
            ->findOneBy(['token' => $token]);

        if ($refreshToken) {
            $refreshToken->revoke();
            $this->em->flush();
        }
    }

    public function blacklistToken(string $token): void
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $expiresAt = new \DateTime();
            $expiresAt->setTimestamp($decoded->exp);

            $blacklistedToken = new BlacklistedToken($token, $expiresAt);

            $this->em->persist($blacklistedToken);
            $this->em->flush();

            $this->cleanExpiredTokens();
        } catch (\Exception $e) {
            return;
        }
    }

    public function isBlacklisted(string $token): bool
    {
        $blacklistedToken = $this->em->getRepository(BlacklistedToken::class)
            ->findOneBy(['token' => $token]);

        return $blacklistedToken !== null && !$blacklistedToken->isExpired();
    }

    private function cleanExpiredTokens(): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->delete(BlacklistedToken::class, 'bt')
           ->where('bt.expiresAt < :now')
           ->setParameter('now', new \DateTime());

        $qb->getQuery()->execute();
    }

    private function createAccessToken(User $user): string
    {
        $payload = [
            'uid' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat' => time(),
            'exp' => time() + (int)$_ENV['JWT_EXPIRATION'],
            'iss' => $_ENV['APP_URL']
        ];

        return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public function extractTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        if (!isset($headers['Jrg-Authorization'])) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $headers['Jrg-Authorization'], $matches)) {
            return $matches[1];
        }

        return null;
    }
}