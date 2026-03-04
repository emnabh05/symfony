<?php

namespace App\Repository;

use App\Entity\MonthlyXpWinner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonthlyXpWinner>
 */
class MonthlyXpWinnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyXpWinner::class);
    }

    public function findOneByMonthKey(string $monthKey): ?MonthlyXpWinner
    {
        return $this->findOneBy(['monthKey' => $monthKey]);
    }

    /**
     * @return array<int, MonthlyXpWinner>
     */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.monthKey', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}

