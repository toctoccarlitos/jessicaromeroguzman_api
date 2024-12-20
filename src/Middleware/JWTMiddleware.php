<?php
namespace App\Middleware;

use App\Core\Request;
use App\Service\TokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTMiddleware
{
    private const EXCLUDED_ROUTES = [
        '/api/login',
        '/api/status',
        '/api/refresh'
    ];

    private TokenService $tokenService;

    public function __construct()
    {
        $this->tokenService = new TokenService(app()->em);
    }

    public function handle(Request $request): bool
    {
        $path = $request->getUrl();

        if (in_array($path, self::EXCLUDED_ROUTES)) {
            return true;
        }

        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            return false;
        }

        $jwt = str_replace('Bearer ', '', $headers['Authorization']);

        try {
            if ($this->tokenService->isBlacklisted($jwt)) {
                return false;
            }

            $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET'], 'HS256'));

            if (time() >= $decoded->exp) {
                return false;
            }

            $request->setUser($decoded);
            return true;

        } catch (\Exception $e) {
            return false;
        }
    }
}