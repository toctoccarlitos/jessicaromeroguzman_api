<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class CacheService
{
    private EntityManagerInterface $em;
    private array $config;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->config = [
            'default_ttl' => 3600 // 1 hora
        ];
    }

    public function get(string $key, $default = null)
    {
        try {
            logger()->debug('Getting cached value', ['key' => $key]);

            $conn = $this->em->getConnection();
            $result = $conn->executeQuery(
                'SELECT value, expires_at FROM cache WHERE `key` = ? AND (expires_at > NOW() OR expires_at IS NULL)',
                [$key]
            )->fetchAssociative();

            if (!$result) {
                logger()->debug('Cache miss', ['key' => $key]);
                return $default;
            }

            logger()->debug('Cache hit', ['key' => $key]);
            return json_decode($result['value'], true);
        } catch (\Exception $e) {
            logger()->error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            $conn = $this->em->getConnection();
            $ttl = $ttl ?? $this->config['default_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

            logger()->debug('Setting cache value', [
                'key' => $key,
                'ttl' => $ttl,
                'expires_at' => $expiresAt
            ]);

            $conn->executeStatement(
                'INSERT INTO cache (`key`, value, expires_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), expires_at = VALUES(expires_at)',
                [$key, json_encode($value), $expiresAt]
            );

            logger()->info('Cache set successfully', ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            logger()->error('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            logger()->debug('Deleting cache key', ['key' => $key]);

            $conn = $this->em->getConnection();
            $conn->executeStatement(
                'DELETE FROM cache WHERE `key` = ?',
                [$key]
            );

            logger()->info('Cache key deleted', ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            logger()->error('Cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function flush(): bool
    {
        try {
            logger()->warning('Flushing entire cache');

            $conn = $this->em->getConnection();
            $conn->executeStatement('DELETE FROM cache');

            logger()->info('Cache flushed successfully');
            return true;
        } catch (\Exception $e) {
            logger()->error('Cache flush failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}