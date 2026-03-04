<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findBySupplement(int $supplementId, int $limit = 20): array
    {
        try {
            return $this->createQueryBuilder('r')
                ->andWhere('r.supplement = :supplementId')
                ->setParameter('supplementId', $supplementId)
                ->orderBy('r.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (TableNotFoundException) {
            return [];
        }
    }

    /**
     * @return array{avg: float, count: int}
     */
    public function getSummaryForSupplement(int $supplementId): array
    {
        try {
            $result = $this->createQueryBuilder('r')
                ->select('AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count')
                ->andWhere('r.supplement = :supplementId')
                ->setParameter('supplementId', $supplementId)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (TableNotFoundException) {
            return ['avg' => 0.0, 'count' => 0];
        }

        return [
            'avg' => isset($result['avg_rating']) ? (float) $result['avg_rating'] : 0.0,
            'count' => isset($result['review_count']) ? (int) $result['review_count'] : 0,
        ];
    }

    /**
     * @param int[] $supplementIds
     * @return array<int, array{avg: float, count: int}>
     */
    public function getSummaryForSupplements(array $supplementIds): array
    {
        if (count($supplementIds) === 0) {
            return [];
        }

        try {
            $rows = $this->createQueryBuilder('r')
                ->select('IDENTITY(r.supplement) AS supplement_id, AVG(r.rating) AS avg_rating, COUNT(r.id) AS review_count')
                ->andWhere('r.supplement IN (:supplementIds)')
                ->setParameter('supplementIds', $supplementIds)
                ->groupBy('supplement_id')
                ->getQuery()
                ->getArrayResult();
        } catch (TableNotFoundException) {
            return [];
        }

        $summary = [];
        foreach ($rows as $row) {
            $supplementId = (int) $row['supplement_id'];
            $summary[$supplementId] = [
                'avg' => (float) $row['avg_rating'],
                'count' => (int) $row['review_count'],
            ];
        }

        return $summary;
    }
}
