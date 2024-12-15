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

        $token = $this->authService->authenticate($data['email'], $data['password']);

        if (!$token) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        return $this->json([
            'status' => 'success',
            'token' => $token
        ]);
    }
}