<?php
namespace App\Service;

use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

class ContactService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getMessages(int $page = 1, int $limit = 10, string $sort = 'createdAt', 
                              string $order = 'DESC', string $search = '', string $status = '', 
                              ?string $lastCheck = null): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('m')
           ->from(ContactMessage::class, 'm');

        if ($search) {
            $qb->andWhere('m.name LIKE :search OR m.email LIKE :search OR m.message LIKE :search')
               ->setParameter('search', "%$search%");
        }

        if ($status) {
            $qb->andWhere('m.status = :status')
               ->setParameter('status', $status);
        }

        // Para polling: obtener solo mensajes nuevos desde última verificación
        if ($lastCheck) {
            $lastCheckDate = new \DateTime($lastCheck);
            $qb->andWhere('m.createdAt > :lastCheck')
               ->setParameter('lastCheck', $lastCheckDate);
        }

        $qb->orderBy("m.$sort", $order);

        // Contar total
        $countQb = clone $qb;
        $total = count($countQb->getQuery()->getResult());

        // Agregar paginación
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $messages = $qb->getQuery()->getResult();

        return [
            'items' => array_map(function($message) {
                return [
                    'id' => $message->getId(),
                    'name' => $message->getName(),
                    'email' => $message->getEmail(),
                    'phone' => $message->getPhone(),
                    'message' => $message->getMessage(),
                    'status' => $message->getStatus(),
                    'isRead' => $message->isRead(),
                    'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s')
                ];
            }, $messages),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ],
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
    }
}