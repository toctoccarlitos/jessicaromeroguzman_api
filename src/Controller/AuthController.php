<?php
namespace App\Controller;

use App\Core\Request;
use App\Service\AuthService;

class AuthController extends BaseController
{
    private AuthService $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    public function login(Request $request): string
    {
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

        $tokens = $this->tokenService->createTokenPair($user);

        return $this->json([
            'status' => 'success',
            'data' => $tokens
        ]);
    }

    public function refresh(Request $request): string
    {
        $data = $request->getBody();
        
        if (!isset($data['refresh_token'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Refresh token is required'
            ], 400);
        }

        $tokens = $this->tokenService->refreshTokens($data['refresh_token']);
        
        if (!$tokens) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        return $this->json([
            'status' => 'success',
            'data' => $tokens
        ]);
    }

    public function logout(Request $request): string
    {
        // Obtener el token actual de la cabecera Authorization
        $currentToken = $this->tokenService->extractTokenFromHeader();
        if ($currentToken) {
            // Agregar el token actual a la blacklist
            $this->tokenService->blacklistToken($currentToken);
        }

        // Revocar el refresh token
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