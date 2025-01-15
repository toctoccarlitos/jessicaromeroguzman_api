<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;

class ActivityService
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function logActivity(?User $user, string $type, string $description, ?array $metadata = null): ActivityLog
    {
        $activity = new ActivityLog($type, $description);

        // Asegurar que siempre capturemos IP y User Agent
        $activity->setIpAddress($_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
        $activity->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        // Agregar información básica a metadata si no existe
        if (!$metadata) {
            $metadata = [];
        }

        // Agregar información del request a metadata
        $metadata['request_info'] = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        $activity->setMetadata($metadata);

        // Solo establecer el usuario si se proporciona
        if ($user !== null) {
            $activity->setUser($user);
        }

        if ($metadata) {
            $activity->setMetadata($metadata);
        }

        $this->em->persist($activity);
        $this->em->flush();

        return $activity;
    }

    public function getUserActivity(User $user, int $page = 1, int $limit = 10): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
           ->from(ActivityLog::class, 'a')
           ->where('a.user = :user')
           ->setParameter('user', $user)
           ->orderBy('a.createdAt', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $activities = $qb->getQuery()->getResult();

        // Get total count
        $countQb = $this->em->createQueryBuilder();
        $countQb->select('COUNT(a.id)')
                ->from(ActivityLog::class, 'a')
                ->where('a.user = :user')
                ->setParameter('user', $user);

        $total = $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => $activities,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
}