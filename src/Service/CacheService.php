<?php
namespace App\Service;

use App\Service\Logger\AppLogger;
use Doctrine\ORM\EntityManagerInterface;

class CacheService
{
    private EntityManagerInterface $em;
    private AppLogger $logger;
    private array $config;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->logger = new AppLogger();
        $this->config = [
            'default_ttl' => 3600 // 1 hora
        ];
    }

    public function get(string $key, $default = null)
    {
        try {
            $conn = $this->em->getConnection();
            $result = $conn->executeQuery(
                'SELECT value, expires_at FROM cache WHERE `key` = ? AND (expires_at > NOW() OR expires_at IS NULL)',
                [$key]
            )->fetchAssociative();

            return $result ? json_decode($result['value'], true) : $default;
        } catch (\Exception $e) {
            $this->logger->error('Cache get failed', ['key' => $key], $e);
            return $default;
        }
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            $conn = $this->em->getConnection();
            $ttl = $ttl ?? $this->config['default_ttl'];
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

            $conn->executeStatement(
                'INSERT INTO cache (`key`, value, expires_at) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE value = VALUES(value), expires_at = VALUES(expires_at)',
                [$key, json_encode($value), $expiresAt]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Cache set failed', ['key' => $key], $e);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $conn = $this->em->getConnection();
            $conn->executeStatement(
                'DELETE FROM cache WHERE `key` = ?',
                [$key]
            );
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Cache delete failed', ['key' => $key], $e);
            return false;
        }
    }

    public function flush(): bool
    {
        try {
            $conn = $this->em->getConnection();
            $conn->executeStatement('DELETE FROM cache');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Cache flush failed', [], $e);
            return false;
        }
    }
}