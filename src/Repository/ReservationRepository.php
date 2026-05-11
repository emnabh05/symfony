<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    private const LOYALTY_STATUSES = [
        Reservation::STATUS_CONFIRMED,
        Reservation::STATUS_USED,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * @return Reservation[]
     */
    public function findLatestForClientEmail(string $email, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower($email))
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findLatestByEmail(string $email, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function findHistoryByEmail(string $email, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneForEmail(int $id, string $email): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('r.id = :id')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('id', $id)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByEmail(string $email): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{count: int, perMonth: float}
     */
    public function getReservationPaceForEmail(string $email): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS totalCount')
            ->addSelect('MIN(r.dateReservation) AS firstReservationDate')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('statuts', self::LOYALTY_STATUSES)
            ->getQuery()
            ->getSingleResult();

        $count = (int) ($row['totalCount'] ?? 0);
        if ($count <= 0) {
            return ['count' => 0, 'perMonth' => 0.0];
        }

        $firstReservationDate = $row['firstReservationDate'] ?? null;
        if (is_string($firstReservationDate) && $firstReservationDate !== '') {
            $firstReservationDate = new \DateTimeImmutable($firstReservationDate);
        }
        if (!$firstReservationDate instanceof \DateTimeImmutable) {
            return ['count' => $count, 'perMonth' => 0.0];
        }

        $now = new \DateTimeImmutable('now');
        $days = max(1, (int) $firstReservationDate->diff($now)->format('%a'));
        $months = max(1.0, $days / 30.0);

        return [
            'count' => $count,
            'perMonth' => round($count / $months, 2),
        ];
    }

    public function getPopularityScoreForEvent(int $idEvent): float
    {
        $scores = $this->getPopularityScoresForEvents([$idEvent]);

        return (float) ($scores[$idEvent]['score'] ?? 0.0);
    }

    /**
     * @param int[] $eventIds
     * @return array<int, array{score: float, reservationsCount: int, favoritesCount: int, averageRating: float}>
     */
    public function getPopularityScoresForEvents(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
        if ($eventIds === []) {
            return [];
        }

        $connection = $this->getEntityManager()->getConnection();
        $result = [];
        foreach ($eventIds as $eventId) {
            $result[$eventId] = [
                'score' => 0.0,
                'reservationsCount' => 0,
                'favoritesCount' => 0,
                'averageRating' => 0.0,
            ];
        }

        $reservationRows = $connection->executeQuery(
            <<<SQL
SELECT
    reservation.id_event AS event_id,
    COUNT(*) AS reservations_count
FROM reservation
WHERE reservation.id_event IN (:eventIds)
GROUP BY reservation.id_event
SQL,
            ['eventIds' => $eventIds],
            ['eventIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        foreach ($reservationRows as $row) {
            $eventId = (int) ($row['event_id'] ?? 0);
            if ($eventId > 0 && isset($result[$eventId])) {
                $result[$eventId]['reservationsCount'] = (int) ($row['reservations_count'] ?? 0);
            }
        }

        $favoriteRows = $connection->executeQuery(
            <<<SQL
SELECT
    favorites.id_event AS event_id,
    COUNT(*) AS favorites_count
FROM favorites
WHERE favorites.id_event IN (:eventIds)
GROUP BY favorites.id_event
SQL,
            ['eventIds' => $eventIds],
            ['eventIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        foreach ($favoriteRows as $row) {
            $eventId = (int) ($row['event_id'] ?? 0);
            if ($eventId > 0 && isset($result[$eventId])) {
                $result[$eventId]['favoritesCount'] = (int) ($row['favorites_count'] ?? 0);
            }
        }

        $reviewRows = $connection->executeQuery(
            <<<SQL
SELECT
    reviews.id_event AS event_id,
    AVG(reviews.note) AS average_rating
FROM reviews
WHERE reviews.id_event IN (:eventIds)
GROUP BY reviews.id_event
SQL,
            ['eventIds' => $eventIds],
            ['eventIds' => ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        foreach ($reviewRows as $row) {
            $eventId = (int) ($row['event_id'] ?? 0);
            if ($eventId > 0 && isset($result[$eventId])) {
                $result[$eventId]['averageRating'] = round((float) ($row['average_rating'] ?? 0.0), 2);
            }
        }

        foreach ($result as $eventId => $stats) {
            $score = ($stats['reservationsCount'] * 2.0)
                + ($stats['favoritesCount'] * 1.5)
                + ($stats['averageRating'] * 3.0);
            $result[$eventId]['score'] = round($score, 2);
        }

        return $result;
    }

    /**
     * @return Reservation[]
     */
    public function findLatestForParticipant(string $email, ?string $nomParticipant = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->setParameter('email', mb_strtolower($email))
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit);

        if ($nomParticipant !== null && trim($nomParticipant) !== '') {
            $qb->andWhere('LOWER(r.nomParticipant) = :nom')
                ->setParameter('nom', mb_strtolower(trim($nomParticipant)));
        }

        return $qb->getQuery()->getResult();
    }

    public function findByQrToken(string $token): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('r.qrToken = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidForPublicScan(string $token): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('r.qrToken = :token')
            ->setParameter('token', $token)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Reservation[]
     */
    public function listForAdmin(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function searchByParticipantName(string $query): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere('LOWER(r.nomParticipant) LIKE LOWER(:query) OR LOWER(r.emailParticipant) LIKE LOWER(:query)')
            ->setParameter('query', '%'.$needle.'%')
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Reservation[]
     */
    public function searchForCheckin(string $query, int $limit = 100): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('e')
            ->leftJoin('r.event', 'e')
            ->andWhere(
                'LOWER(r.nomParticipant) LIKE :query
                OR LOWER(r.emailParticipant) LIKE :query
                OR LOWER(e.titre) LIKE :query
                OR LOWER(e.lieu) LIKE :query'
            )
            ->setParameter('query', '%'.$needle.'%')
            ->orderBy('r.dateReservation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{countConfirmed: int, totalAmount: float, lastReservationDate: ?\DateTimeImmutable}
     */
    public function getLoyaltyStatsForEmail(string $email): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS countConfirmed')
            ->addSelect('COALESCE(SUM(r.montant), 0) AS totalAmount')
            ->addSelect('MAX(r.dateReservation) AS lastReservationDate')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('statuts', self::LOYALTY_STATUSES)
            ->getQuery()
            ->getSingleResult();

        $lastDate = $row['lastReservationDate'] ?? null;
        if (is_string($lastDate) && $lastDate !== '') {
            $lastDate = new \DateTimeImmutable($lastDate);
        }

        return [
            'countConfirmed' => (int) ($row['countConfirmed'] ?? 0),
            'totalAmount' => (float) ($row['totalAmount'] ?? 0),
            'lastReservationDate' => $lastDate instanceof \DateTimeImmutable ? $lastDate : null,
        ];
    }

    /**
     * @return array<int, array{email: string, confirmedCount: int, loyaltyStatus: string}>
     */
    public function getClientLoyaltyOverview(int $vipThreshold = 5, string $filter = 'all'): array
    {
        $filter = mb_strtolower(trim($filter));
        if (!in_array($filter, ['all', 'vip', 'standard'], true)) {
            $filter = 'all';
        }
        $vipThreshold = max(1, $vipThreshold);

        $connection = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT
    LOWER(r.email_participant) AS email_key,
    MIN(r.email_participant) AS email,
    SUM(CASE WHEN r.statut IN (:statusConfirmed, :statusUsed) THEN 1 ELSE 0 END) AS confirmed_count
FROM reservation r
WHERE TRIM(COALESCE(r.email_participant, '')) <> ''
GROUP BY LOWER(r.email_participant)
SQL;
        if ($filter === 'vip') {
            $sql .= "\nHAVING SUM(CASE WHEN r.statut IN (:statusConfirmed, :statusUsed) THEN 1 ELSE 0 END) >= :vipThreshold";
        } elseif ($filter === 'standard') {
            $sql .= "\nHAVING SUM(CASE WHEN r.statut IN (:statusConfirmed, :statusUsed) THEN 1 ELSE 0 END) < :vipThreshold";
        }
        $sql .= "\nORDER BY confirmed_count DESC, email ASC";

        $rows = $connection->executeQuery($sql, [
            'statusConfirmed' => Reservation::STATUS_CONFIRMED,
            'statusUsed' => Reservation::STATUS_USED,
            'vipThreshold' => $vipThreshold,
        ])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $confirmedCount = (int) ($row['confirmed_count'] ?? 0);
            $result[] = [
                'email' => (string) ($row['email'] ?? ''),
                'confirmedCount' => $confirmedCount,
                'loyaltyStatus' => $confirmedCount >= $vipThreshold ? 'VIP' : 'STANDARD',
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLoyaltyLeaderboard(?string $search = null, string $sort = 'count_desc'): array
    {
        $search = mb_strtolower(trim((string) $search));
        $qb = $this->createQueryBuilder('r')
            ->select('MIN(r.emailParticipant) AS emailParticipant')
            ->addSelect('MAX(r.nomParticipant) AS nomParticipant')
            ->addSelect('COUNT(r.id) AS countConfirmed')
            ->addSelect('COALESCE(SUM(r.montant), 0) AS totalAmount')
            ->addSelect('MAX(r.dateReservation) AS lastReservationDate')
            ->addSelect(
                'CASE
                    WHEN COUNT(r.id) >= 5 THEN 4
                    WHEN COUNT(r.id) >= 4 THEN 3
                    WHEN COUNT(r.id) >= 2 THEN 2
                    WHEN COUNT(r.id) >= 1 THEN 1
                    ELSE 0
                END AS HIDDEN tierRank'
            )
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere("TRIM(COALESCE(r.emailParticipant, '')) <> ''")
            ->setParameter('statuses', self::LOYALTY_STATUSES)
            ->groupBy('r.emailParticipant');

        if ($search !== '') {
            $qb->andWhere('LOWER(r.emailParticipant) LIKE :search OR LOWER(r.nomParticipant) LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        match ($sort) {
            'count_asc' => $qb->orderBy('countConfirmed', 'ASC')->addOrderBy('lastReservationDate', 'DESC'),
            'total_desc' => $qb->orderBy('totalAmount', 'DESC')->addOrderBy('countConfirmed', 'DESC'),
            'total_asc' => $qb->orderBy('totalAmount', 'ASC')->addOrderBy('countConfirmed', 'DESC'),
            'tier_desc' => $qb->orderBy('tierRank', 'DESC')->addOrderBy('countConfirmed', 'DESC'),
            'tier_asc' => $qb->orderBy('tierRank', 'ASC')->addOrderBy('countConfirmed', 'DESC'),
            'date_asc' => $qb->orderBy('lastReservationDate', 'ASC')->addOrderBy('countConfirmed', 'DESC'),
            'date_desc' => $qb->orderBy('lastReservationDate', 'DESC')->addOrderBy('countConfirmed', 'DESC'),
            default => $qb->orderBy('countConfirmed', 'DESC')->addOrderBy('lastReservationDate', 'DESC'),
        };

        $rows = $qb->getQuery()->getArrayResult();

        foreach ($rows as &$row) {
            if (isset($row['countConfirmed'])) {
                $row['countConfirmed'] = (int) $row['countConfirmed'];
            }
            if (isset($row['totalAmount'])) {
                $row['totalAmount'] = (float) $row['totalAmount'];
            }
            if (!empty($row['lastReservationDate'])) {
                $row['lastReservationDate'] = new \DateTimeImmutable((string) $row['lastReservationDate']);
            } else {
                $row['lastReservationDate'] = null;
            }
        }
        unset($row);

        return $rows;
    }
}
