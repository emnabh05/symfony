<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findByEmail(string $email, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.email = :email')
            ->setParameter('email', $email)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByEmail(string $email): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.email = :email')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadForEmail(string $email): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':readAt')
            ->andWhere('n.email = :email')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('readAt', new \DateTime())
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }
}
