<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Service\ActivationService;
use Doctrine\ORM\EntityManager;

class ActivationController extends BaseController
{
    private ActivationService $activationService;
    private EntityManager $em;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->activationService = new ActivationService(
            $em,
            new \App\Service\EmailService()
        );
        $this->em = $em;
    }

    public function activate(Request $request): string
    {
        $data = $request->getBody();

        if (!isset($data['token']) || !isset($data['password'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Token y contraseña son requeridos'
            ], 400);
        }

        // Validar la contraseña
        if (strlen($data['password']) < 8) {
            return $this->json([
                'status' => 'error',
                'message' => 'La contraseña debe tener al menos 8 caracteres'
            ], 400);
        }

        if ($this->activationService->activateUser($data['token'], $data['password'])) {
            return $this->json([
                'status' => 'success',
                'message' => 'Cuenta activada exitosamente'
            ]);
        }

        return $this->json([
            'status' => 'error',
            'message' => 'Token inválido o expirado'
        ], 400);
    }

    public function resendActivation(Request $request): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 403);
        }

        $data = $request->getBody();
        if (!isset($data['user_id'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'ID de usuario requerido'
            ], 400);
        }

        $user = $this->em->getRepository(User::class)->find($data['user_id']);
        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $token = $this->activationService->resendActivation($user);
        if (!$token) {
            return $this->json([
                'status' => 'error',
                'message' => 'No se puede reenviar la activación para este usuario'
            ], 400);
        }

        return $this->json([
            'status' => 'success',
            'message' => 'Email de activación reenviado'
        ]);
    }
}