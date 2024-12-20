<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\ActivityLog;
use App\Service\AuthService;
use App\Service\ActivityService;

class AuthController extends BaseController
{
    private AuthService $authService;
    private ActivityService $activityService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
        $this->activityService = new ActivityService(app()->em);
    }

    public function login(Request $request): string
    {
        // El login ya se registra en AuthService
        $data = $request->getBody();

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email and password are required'
            ], 400);
        }

        $user = $this->authService->authenticate($data['email'], $data['password']);

        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Los tokens
        $currentToken = $this->tokenService->extractTokenFromHeader();
        if ($currentToken) {
            $this->tokenService->blacklistToken($currentToken);
        }

        $tokens = $this->tokenService->createTokenPair($user);

        return $this->json([
            'status' => 'success',
            'data' => $tokens
        ]);
    }

    public function logout(Request $request): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $currentToken = $this->tokenService->extractTokenFromHeader();

        if (!$currentToken) {
            return $this->json([
                'status' => 'error',
                'message' => 'No token provided'
            ], 400);
        }

        // Registrar el logout
        $user = app()->em->getRepository(\App\Entity\User::class)
            ->find($request->getUserId());
            
        if ($user) {
            $this->activityService->logActivity(
                $user,
                ActivityLog::TYPE_LOGOUT,
                'Usuario ha cerrado sesión',
                [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            );
        }

        $this->tokenService->blacklistToken($currentToken);

        $data = $request->getBody();
        if (isset($data['refresh_token'])) {
            $this->tokenService->revokeRefreshToken($data['refresh_token']);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }
}