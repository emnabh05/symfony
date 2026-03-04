<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

class EventCapacityService
{
    private const ACTIVE_PLACE_STATUSES = [
        Reservation::STATUS_CONFIRMED,
        Reservation::STATUS_USED,
        'EN_ATTENTE_PAIEMENT',
        'PAYEE',
        'Payee',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function countActiveReservations(Event $event): int
    {
        $eventId = (int) ($event->getId() ?? 0);
        if ($eventId <= 0) {
            return 0;
        }

        return (int) $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('IDENTITY(r.event) = :eventId')
            ->andWhere('r.statut IN (:allowed)')
            ->setParameter('eventId', $eventId)
            ->setParameter('allowed', self::ACTIVE_PLACE_STATUSES)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $eventIds
     * @return array<int, int>
     */
    public function countActiveReservationsByEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('IDENTITY(r.event) AS eventId, COUNT(r.id) AS totalCount')
            ->where('IDENTITY(r.event) IN (:eventIds)')
            ->andWhere('r.statut IN (:allowed)')
            ->setParameter('eventIds', $eventIds)
            ->setParameter('allowed', self::ACTIVE_PLACE_STATUSES)
            ->groupBy('eventId')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) ($row['eventId'] ?? 0)] = (int) ($row['totalCount'] ?? 0);
        }

        return $counts;
    }

    public function remainingPlaces(Event $event): int
    {
        $capacity = max(0, (int) $event->getCapacite());
        $count = $this->countActiveReservations($event);

        return max(0, $capacity - $count);
    }

    public function isFull(Event $event): bool
    {
        return $this->countActiveReservations($event) >= max(0, (int) $event->getCapacite());
    }

    // Backward-compatible aliases used by existing module code.
    public function getConfirmedReservationsCount(Event $event): int
    {
        return $this->countActiveReservations($event);
    }

    public function getRemainingPlaces(Event $event): int
    {
        return $this->remainingPlaces($event);
    }

    public function isEventFull(Event $event): bool
    {
        return $this->isFull($event);
    }
}
