<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use App\Service\Logger\AppLogger;

class UserController extends BaseController
{
    private EntityManager $em;
    private AppLogger $logger;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
        $this->logger = new AppLogger();
    }

    public function profile(Request $request): string
    {
        $userId = $request->getUserId();
        if (!$userId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Unauthorized - No user ID'
            ], 401);
        }

        $user = $this->em->getRepository(User::class)
            ->find($userId);

        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'status' => $user->getStatus(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    public function listUsers(Request $request): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        // Obtener parámetros de query
        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 10));
        $search = $request->getQuery('search', '');
        $status = $request->getQuery('status', '');
        $role = $request->getQuery('role', '');

        // Crear query builder
        $qb = $this->em->createQueryBuilder();
        $qb->select('u')
        ->from(User::class, 'u');

        // Aplicar filtros
        if ($search) {
            $qb->andWhere('u.email LIKE :search')
            ->setParameter('search', "%$search%");
        }

        if ($status) {
            $qb->andWhere('u.status = :status')
            ->setParameter('status', $status);
        }

        if ($role) {
            $qb->andWhere('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role));
        }

        // Obtener total
        $total = clone $qb;
        $totalItems = count($total->getQuery()->getResult());

        // Aplicar paginación
        $qb->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->orderBy('u.createdAt', 'DESC');

        $users = $qb->getQuery()->getResult();

        // Formatear resultados
        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'status' => $user->getStatus(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $users);

        return $this->json([
            'status' => 'success',
            'data' => [
                'items' => $data,
                'pagination' => [
                    'total' => $totalItems,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($totalItems / $limit)
                ]
            ]
        ]);
    }

    public function create(Request $request): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $data = $request->getBody();

        // Validación básica
        if (!isset($data['email']) || !isset($data['roles'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email and roles are required'
            ], 400);
        }

        // Verificar si el email ya existe
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return $this->json([
                'status' => 'error',
                'message' => 'Email already exists'
            ], 400);
        }

        try {
            $user = new User();
            $user->setEmail($data['email'])
                ->setRoles($data['roles'])
                ->setStatus(User::STATUS_PENDING)  // Cambiar a PENDING
                ->setPassword(bin2hex(random_bytes(8))); // Contraseña temporal

            $this->em->persist($user);
            $this->em->flush();

            // Crear y enviar token de activación
            $activationService = new \App\Service\ActivationService(
                $this->em,
                new \App\Service\EmailService()
            );

            $token = $activationService->createActivationToken($user);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus()
                ]
            ], 201);

        } catch (\Exception $e) {
            // Log el error
            $logger = new \App\Service\Logger\AppLogger();
            $logger->error('Error creating user', ['email' => $data['email']], $e);

            return $this->json([
                'status' => 'error',
                'message' => 'Error creating user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id): string
    {
        try {
            if (!$request->hasRole('ROLE_ADMIN')) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            $data = $request->getBody();
            $user = $this->em->getRepository(User::class)->find($id);

            if (!$user) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }

            if (array_key_exists('roles', $data)) {
                $user->setRoles($data['roles']);
            }

            if (isset($data['status'])) {
                $user->setStatus($data['status']);
            }

            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus(),
                    'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error updating user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, int $id): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        try {
            $user->setStatus(User::STATUS_BLOCKED);
            $this->em->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'User deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Error deactivating user'
            ], 500);
        }
    }

    public function changePassword(Request $request): string
    {
        if (!$request->getUser()) {
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $data = $request->getBody();

        // Validar entrada
        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Contraseña actual y nueva son requeridas'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user || !$user->verifyPassword($data['current_password'])) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Contraseña actual incorrecta'
                ], 400);
            }

            // Validar nueva contraseña
            if (strlen($data['new_password']) < 8) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'La nueva contraseña debe tener al menos 8 caracteres'
                ], 400);
            }

            // Actualizar contraseña
            $user->setPassword($data['new_password']);
            $this->em->flush();

            $this->logger->info('Contraseña cambiada exitosamente', [
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error cambiando contraseña', [], $e);
            return $this->json([
                'status' => 'error',
                'message' => 'Error al cambiar la contraseña'
            ], 500);
        }
    }
}