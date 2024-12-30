<?php
namespace App\Controller;

use App\Core\Request;
use App\Entity\User;
use Doctrine\ORM\EntityManager;

class UserController extends BaseController
{
    private EntityManager $em;

    public function __construct(EntityManager $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    public function profile(Request $request): string
    {
        $userId = $request->getUserId();
        if (!$userId) {
            logger()->warning('Unauthorized profile access attempt');
            return $this->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $user = $this->em->getRepository(User::class)
            ->find($userId);

        if (!$user) {
            logger()->warning('Profile not found', ['user_id' => $userId]);
            return $this->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        logger()->info('Profile accessed', ['user_id' => $userId]);
        return $this->json([
            'status' => 'success',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'birthDate' => $user->getBirthDate()->format('Y-m-d'),
                'mobilePhone' => $user->getMobilePhone(),
                'roles' => $user->getRoles(),
                'status' => $user->getStatus(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    public function listUsers(Request $request): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            logger()->warning('Unauthorized users list access attempt', [
                'user_id' => $request->getUserId()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $page = (int)($request->getQuery('page', 1));
        $limit = (int)($request->getQuery('limit', 10));
        $search = $request->getQuery('search', '');
        $status = $request->getQuery('status', '');
        $role = $request->getQuery('role', '');

        logger()->debug('Listing users', [
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'status' => $status,
            'role' => $role
        ]);

        $qb = $this->em->createQueryBuilder();
        $qb->select('u')
        ->from(User::class, 'u');

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

        $total = clone $qb;
        $totalItems = count($total->getQuery()->getResult());

        $qb->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit)
        ->orderBy('u.createdAt', 'DESC');

        $users = $qb->getQuery()->getResult();

        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'status' => $user->getStatus(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }, $users);

        logger()->info('Users list retrieved', [
            'total' => $totalItems,
            'page' => $page,
            'filtered_count' => count($users)
        ]);

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
            logger()->warning('Unauthorized user creation attempt', [
                'user_id' => $request->getUserId()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $data = $request->getBody();

        if (!isset($data['email']) || !isset($data['roles']) || 
            !isset($data['firstName']) || !isset($data['lastName']) || 
            !isset($data['birthDate']) || !isset($data['mobilePhone'])) {
            logger()->warning('Invalid user creation data', ['data' => $data]);
            return $this->json([
                'status' => 'error',
                'message' => 'All fields are required: email, roles, firstName, lastName, birthDate, mobilePhone'
            ], 400);
        }

        try {
            $birthDate = new \DateTime($data['birthDate']);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid birth date format'
            ], 400);
        }

        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            logger()->warning('Duplicate email in user creation', [
                'email' => $data['email']
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Email already exists'
            ], 400);
        }

        try {
            $user = new User();
            $user->setEmail($data['email'])
                ->setRoles($data['roles'])
                ->setStatus(User::STATUS_PENDING)
                ->setPassword(bin2hex(random_bytes(8)))
                ->setFirstName($data['firstName'])
                ->setLastName($data['lastName'])
                ->setBirthDate($birthDate)
                ->setMobilePhone($data['mobilePhone']);

            $this->em->persist($user);
            $this->em->flush();

            $activationService = new \App\Service\ActivationService(
                $this->em,
                new \App\Service\EmailService()
            );

            $token = $activationService->createActivationToken($user);

            logger()->info('User created successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'birthDate' => $user->getBirthDate()->format('Y-m-d'),
                    'mobilePhone' => $user->getMobilePhone(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus()
                ]
            ], 201);

        } catch (\Exception $e) {
            logger()->error('Error creating user', [
                'email' => $data['email'],
                'error' => $e->getMessage()
            ]);

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
                logger()->warning('Unauthorized user update attempt', [
                    'user_id' => $request->getUserId(),
                    'target_user_id' => $id
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Access denied'
                ], 403);
            }

            $data = $request->getBody();
            $user = $this->em->getRepository(User::class)->find($id);

            if (!$user) {
                logger()->warning('User not found for update', ['user_id' => $id]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }

            if (isset($data['firstName'])) {
                $user->setFirstName($data['firstName']);
            }

            if (isset($data['lastName'])) {
                $user->setLastName($data['lastName']);
            }

            if (isset($data['birthDate'])) {
                try {
                    $birthDate = new \DateTime($data['birthDate']);
                    $user->setBirthDate($birthDate);
                } catch (\Exception $e) {
                    return $this->json([
                        'status' => 'error',
                        'message' => 'Invalid birth date format'
                    ], 400);
                }
            }

            if (isset($data['mobilePhone'])) {
                $user->setMobilePhone($data['mobilePhone']);
            }

            if (array_key_exists('roles', $data)) {
                $user->setRoles($data['roles']);
            }

            if (isset($data['status'])) {
                $user->setStatus($data['status']);
            }

            $this->em->flush();

            logger()->info('User updated successfully', [
                'user_id' => $id,
                'updates' => array_keys($data)
            ]);

            return $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'birthDate' => $user->getBirthDate()->format('Y-m-d'),
                    'mobilePhone' => $user->getMobilePhone(),
                    'roles' => $user->getRoles(),
                    'status' => $user->getStatus(),
                    'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            logger()->error('Error updating user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Error updating user: ' . $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request, int $id): string
    {
        if (!$request->hasRole('ROLE_ADMIN')) {
            logger()->warning('Unauthorized user deletion attempt', [
                'user_id' => $request->getUserId(),
                'target_user_id' => $id
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Access denied'
            ], 403);
        }

        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            logger()->warning('User not found for deletion', ['user_id' => $id]);
            return $this->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        try {
            $user->setStatus(User::STATUS_BLOCKED);
            $this->em->flush();

            logger()->info('User deactivated successfully', ['user_id' => $id]);
            return $this->json([
                'status' => 'success',
                'message' => 'User deactivated successfully'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error deactivating user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Error deactivating user'
            ], 500);
        }
    }

    public function changePassword(Request $request): string
    {
        if (!$request->getUser()) {
            logger()->warning('Unauthorized password change attempt');
            return $this->json([
                'status' => 'error',
                'message' => 'No autorizado'
            ], 401);
        }

        $data = $request->getBody();

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            logger()->warning('Invalid password change data', [
                'user_id' => $request->getUserId()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Contraseña actual y nueva son requeridas'
            ], 400);
        }

        try {
            $user = $this->em->getRepository(User::class)
                ->find($request->getUserId());

            if (!$user || !$user->verifyPassword($data['current_password'])) {
                logger()->warning('Invalid current password', [
                    'user_id' => $request->getUserId()
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'Contraseña actual incorrecta'
                ], 400);
            }

            if (strlen($data['new_password']) < 8) {
                logger()->warning('Invalid new password length', [
                    'user_id' => $request->getUserId()
                ]);
                return $this->json([
                    'status' => 'error',
                    'message' => 'La nueva contraseña debe tener al menos 8 caracteres'
                ], 400);
            }

            $user->setPassword($data['new_password']);
            $this->em->flush();

            logger()->info('Password changed successfully', [
                'user_id' => $user->getId()
            ]);

            return $this->json([
                'status' => 'success',
                'message' => 'Contraseña actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            logger()->error('Error changing password', [
                'user_id' => $request->getUserId(),
                'error' => $e->getMessage()
            ]);
            return $this->json([
                'status' => 'error',
                'message' => 'Error al cambiar la contraseña'
            ], 500);
        }
    }
}