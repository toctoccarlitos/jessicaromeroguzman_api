<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use App\Service\ActivityService;

class ActivityController extends BaseController
{
    private ActivityService $activityService;

    public function __construct()
    {
        parent::__construct();
        $this->activityService = new ActivityService(app()->em);
    }

    public function getUserActivity(Request $request): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 10));

        // Obtener el ID del usuario del que queremos ver el historial
        $targetUserId = (int)($request->getQuery('user_id', $request->getUserId()));

        // Si el ID solicitado no es el del usuario autenticado, verificar que sea admin
        if ($targetUserId !== $request->getUserId() && !$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'No tiene permisos para ver el historial de otros usuarios'
            ], 403);
        }

        $user = app()->em->getRepository(User::class)
            ->find($targetUserId);

        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $result = $this->activityService->getUserActivity($user, $page, $limit);

        $activities = array_map(function($activity) {
            return [
                'id' => $activity->getId(),
                'type' => $activity->getType(),
                'description' => $activity->getDescription(),
                'ip_address' => $activity->getIpAddress(),
                'user_agent' => $activity->getUserAgent(),
                'metadata' => $activity->getMetadata(),
                'created_at' => $activity->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $result['items']);

        return $this->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                    'last_login_ip' => $user->getLastLoginIp()
                ],
                'items' => $activities,
                'pagination' => $result['pagination']
            ]
        ]);
    }

    public function getUserDetails(Request $request, int $userId): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        // Solo el admin o el propio usuario pueden ver los detalles
        if ($userId !== $request->getUserId() && !$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'No tiene permisos para ver los detalles de este usuario'
            ], 403);
        }

        $user = app()->em->getRepository(User::class)
            ->find($userId);

        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Obtener las últimas 5 actividades
        $recentActivity = $this->activityService->getUserActivity($user, 1, 5);

        return $this->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'birthDate' => $user->getBirthDate()->format('Y-m-d'),
                    'mobilePhone' => $user->getMobilePhone(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                    'last_login_ip' => $user->getLastLoginIp(),
                    'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s')
                ],
                'recent_activity' => array_map(function($activity) {
                    return [
                        'type' => $activity->getType(),
                        'description' => $activity->getDescription(),
                        'created_at' => $activity->getCreatedAt()->format('Y-m-d H:i:s')
                    ];
                }, $recentActivity['items'])
            ]
        ]);
    }
}