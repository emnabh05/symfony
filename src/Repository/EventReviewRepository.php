<?php

namespace App\Repository;

use App\Entity\EventReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventReview>
 */
class EventReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventReview::class);
    }

    /**
     * @return EventReview[]
     */
    public function findByEvent(int $idEvent, string $sort = 'recent'): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('IDENTITY(r.event) = :idEvent')
            ->setParameter('idEvent', $idEvent);

        if ($sort === 'best') {
            $qb->orderBy('r.note', 'DESC')->addOrderBy('r.createdAt', 'DESC');
        } else {
            $qb->orderBy('r.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{average: float, count: int}
     */
    public function getStatsForEvent(int $idEvent): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.note) AS avg_note, COUNT(r.id) AS reviews_count')
            ->where('IDENTITY(r.event) = :idEvent')
            ->setParameter('idEvent', $idEvent)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'average' => isset($row['avg_note']) ? (float) $row['avg_note'] : 0.0,
            'count' => isset($row['reviews_count']) ? (int) $row['reviews_count'] : 0,
        ];
    }

    public function findOneByEmailAndEvent(string $emailParticipant, int $idEvent): ?EventReview
    {
        return $this->createQueryBuilder('r')
            ->where('IDENTITY(r.event) = :idEvent')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('idEvent', $idEvent)
            ->setParameter('email', mb_strtolower(trim($emailParticipant)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $eventIds
     * @return array<int, array{average: float, count: int}>
     */
    public function getStatsForEvents(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.event) AS event_id, AVG(r.note) AS avg_note, COUNT(r.id) AS reviews_count')
            ->where('IDENTITY(r.event) IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->groupBy('event_id')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['event_id']] = [
                'average' => (float) $row['avg_note'],
                'count' => (int) $row['reviews_count'],
            ];
        }

        return $result;
    }
}
