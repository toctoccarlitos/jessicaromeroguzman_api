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
        // Debug
        var_dump([
            'user_data' => $request->getUser(),
            'user_id' => $request->getUserId()
        ]);

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
}