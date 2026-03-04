<?php

namespace App\Repository;

use App\Entity\QrLoginSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QrLoginSession>
 */
class QrLoginSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QrLoginSession::class);
    }

    public function findByRawToken(string $token): ?QrLoginSession
    {
        if ($token === '') {
            return null;
        }

        return $this->findOneBy(['tokenHash' => hash('sha256', $token)]);
    }

    public function cleanupOldSessions(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('q')
            ->delete()
            ->where('q.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
