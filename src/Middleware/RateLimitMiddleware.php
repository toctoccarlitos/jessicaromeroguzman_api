<?php
namespace App\Middleware;

use App\Core\Request;
use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;

class RateLimitMiddleware
{
    private const WINDOW = 3600; // 1 hora
    private const MAX_REQUESTS = 1000;

    private CacheService $cache;

    public function __construct(EntityManagerInterface $em)
    {
        $this->cache = new CacheService($em);
    }

    public function handle(Request $request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "ratelimit:{$ip}";

        $requests = $this->cache->get($key, 0);

        if ($requests >= self::MAX_REQUESTS) {
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode([
                'error' => 'Rate limit exceeded'
            ]);
            return false;
        }

        $this->cache->set($key, $requests + 1, self::WINDOW);
        return true;
    }
}