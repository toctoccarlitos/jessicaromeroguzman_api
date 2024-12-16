<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Predis\Client;

class TokenService
{
    private Client $redis;

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port'   => $_ENV['REDIS_PORT'] ?? 6379,
        ]);
    }

    public function createTokenPair(User $user): array
    {
        // Crear access token (JWT)
        $accessToken = $this->createAccessToken($user);

        // Crear refresh token
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

        // Revocar el refresh token usado
        $refreshTokenEntity->revoke();

        // Crear nuevos tokens
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

    public function revokeAllUserTokens(User $user): void
    {
        $tokens = $this->em->getRepository(RefreshToken::class)
            ->findBy(['user' => $user, 'isRevoked' => false]);

        foreach ($tokens as $token) {
            $token->revoke();
        }

        $this->em->flush();
    }

    public function blacklistToken(string $token): void
    {
        $key = "blacklisted_token:" . $token;
        $ttl = $this->getRemainingTTL($token);

        // Debug
        var_dump([
            'blacklisting_token' => $token,
            'ttl' => $ttl,
            'key' => $key
        ]);

        $this->redis->setex($key, $ttl, 'blacklisted');
    }

    public function isBlacklisted(string $token): bool
    {
        $key = "blacklisted_token:" . $token;
        $result = $this->redis->exists($key);

        // Debug
        var_dump([
            'checking_blacklist' => $token,
            'key' => $key,
            'is_blacklisted' => (bool)$result
        ]);

        return (bool)$result;
    }

    private function getRemainingTTL(string $token): int
    {
        try {
            $payload = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $ttl = $payload->exp - time();
            return max($ttl, 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function extractTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }

        return null;
    }
}