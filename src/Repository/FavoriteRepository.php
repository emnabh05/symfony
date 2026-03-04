<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function isFavorited(string $emailParticipant, int $idEvent): bool
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('IDENTITY(f.event) = :idEvent')
            ->andWhere('LOWER(f.emailParticipant) = :email')
            ->setParameter('idEvent', $idEvent)
            ->setParameter('email', mb_strtolower(trim($emailParticipant)))
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countByEvent(int $idEvent): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('IDENTITY(f.event) = :idEvent')
            ->setParameter('idEvent', $idEvent)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Favorite[]
     */
    public function findByEmail(string $emailParticipant, int $limit = 100): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('e')
            ->join('f.event', 'e')
            ->where('LOWER(f.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower(trim($emailParticipant)))
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Event[]
     */
    public function findEventsFavoritedByEmail(string $emailParticipant): array
    {
        $favorites = $this->findByEmail($emailParticipant, 300);

        return array_values(array_filter(array_map(
            static fn (Favorite $favorite): ?Event => $favorite->getEvent(),
            $favorites
        )));
    }

    /**
     * @param int[] $eventIds
     * @return int[]
     */
    public function findFavoritedEventIdsByEmail(string $emailParticipant, array $eventIds = []): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.event) AS event_id')
            ->where('LOWER(f.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower(trim($emailParticipant)));

        if ($eventIds !== []) {
            $qb->andWhere('IDENTITY(f.event) IN (:eventIds)')
                ->setParameter('eventIds', array_values(array_unique(array_map('intval', $eventIds))));
        }

        $rows = $qb->getQuery()->getArrayResult();
        return array_values(array_map(static fn (array $row): int => (int) $row['event_id'], $rows));
    }

    /**
     * @param int[] $eventIds
     * @return array<int, int>
     */
    public function getCountsForEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.event) AS event_id, COUNT(f.id) AS favorites_count')
            ->where('IDENTITY(f.event) IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->groupBy('event_id')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['event_id']] = (int) $row['favorites_count'];
        }

        return $result;
    }
}
