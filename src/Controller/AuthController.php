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
                'message' => 'Email and password are required',
            ], 400);
        }

        // 1. Verificar el token de reCAPTCHA
        if (!isset($data['recaptcha_token'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'reCAPTCHA token is required'
            ], 400);
        }

        $recaptchaToken = $data['recaptcha_token'];
        $recaptchaSecret = $_ENV['reCAPTCHA_SECRET_KEY'];
        $recaptchaResponse = $this->verifyRecaptcha($recaptchaToken, $recaptchaSecret);

        if (!$recaptchaResponse['success']) {
            logger()->error('reCAPTCHA verification failed', [
                'response' => $recaptchaResponse
            ]);

            return $this->json([
                'status' => 'error',
                'message' => 'Invalid reCAPTCHA - ',
                'recaptcha_token' => $recaptchaToken,
                'response' => $recaptchaResponse
            ], 400);
        }

        // 2. Verificar la acción (IMPORTANTE para v3)
        if ($recaptchaResponse['action'] !== 'login') { // Comprueba que la acción coincida
            logger()->error('reCAPTCHA action mismatch', [
                'expected' => 'login',
                'received' => $recaptchaResponse['action'] ?? 'none'
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid reCAPTCHA action'
            ], 400);
        }

        // 3. (Opcional) Verificar el score (recomendado)
        if ($recaptchaResponse['score'] < 0.5) { // Ajusta el umbral según tus necesidades
            logger()->error('reCAPTCHA score too low', [
                'score' => $recaptchaResponse['score'] ?? 0
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'reCAPTCHA score too low'
            ], 400);
        }

        // 4. Si reCAPTCHA es válido, continuar con la autenticación
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

    private function verifyRecaptcha(string $token, string $secret): array
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify';

        $data = [
            'secret' => $secret,
            'response' => $token,
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            logger()->error('Error verifying reCAPTCHA', [
                'error' => error_get_last()
            ]);
            return ['success' => false];
        }

        return json_decode($response, true) ?: ['success' => false];
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