<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\WaitlistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistEntry>
 */
class WaitlistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistEntry::class);
    }

    public function findOneByEventAndEmail(Event $event, string $email): ?WaitlistEntry
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.event = :event')
            ->andWhere('LOWER(w.email) = :email')
            ->setParameter('event', $event)
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextPendingForEvent(Event $event): ?WaitlistEntry
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', WaitlistEntry::STATUS_EN_ATTENTE)
            ->orderBy('w.createdAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findNextForEvent(Event $event): ?WaitlistEntry
    {
        return $this->findNextPendingForEvent($event);
    }

    public function countPendingBefore(Event $event, \DateTimeImmutable $createdAt, int $id): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->andWhere('(w.createdAt < :createdAt OR (w.createdAt = :createdAt AND w.id < :id))')
            ->setParameter('event', $event)
            ->setParameter('status', WaitlistEntry::STATUS_EN_ATTENTE)
            ->setParameter('createdAt', $createdAt)
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveInvitesForEvent(Event $event): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', WaitlistEntry::STATUS_INVITE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEventAndStatus(Event $event, string $status): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $eventIds
     * @return array<int, array<string, int>>
     */
    public function getStatusCountsByEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('w')
            ->select('IDENTITY(w.event) AS eventId, w.status AS status, COUNT(w.id) AS total')
            ->andWhere('IDENTITY(w.event) IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->groupBy('eventId, w.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $eventId = (int) ($row['eventId'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            if ($eventId <= 0 || $status === '') {
                continue;
            }
            if (!isset($counts[$eventId])) {
                $counts[$eventId] = [];
            }
            $counts[$eventId][$status] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findExpiredInvites(\DateTimeImmutable $now, int $limit = 200): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('w.expiresAt IS NOT NULL')
            ->andWhere('w.expiresAt < :now')
            ->setParameter('status', WaitlistEntry::STATUS_INVITE)
            ->setParameter('now', $now)
            ->orderBy('w.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findExpiredInvitesForEvent(Event $event, \DateTimeImmutable $now, int $limit = 200): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->andWhere('w.expiresAt IS NOT NULL')
            ->andWhere('w.expiresAt < :now')
            ->setParameter('event', $event)
            ->setParameter('status', WaitlistEntry::STATUS_INVITE)
            ->setParameter('now', $now)
            ->orderBy('w.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findByEventOrdered(Event $event): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.event = :event')
            ->setParameter('event', $event)
            ->orderBy('w.createdAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findInvitesForEmail(string $email, int $limit = 20): array
    {
        return $this->createQueryBuilder('w')
            ->addSelect('e')
            ->join('w.event', 'e')
            ->andWhere('LOWER(w.email) = :email')
            ->andWhere('w.status = :status')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('status', WaitlistEntry::STATUS_INVITE)
            ->orderBy('w.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findByEmail(string $email, int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->addSelect('e')
            ->join('w.event', 'e')
            ->andWhere('LOWER(w.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WaitlistEntry[]
     */
    public function findLatestPromotions(int $limit = 10): array
    {
        return $this->createQueryBuilder('w')
            ->addSelect('e')
            ->join('w.event', 'e')
            ->andWhere('w.status = :status')
            ->setParameter('status', WaitlistEntry::STATUS_CONFIRMEE)
            ->orderBy('w.invitedAt', 'DESC')
            ->addOrderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isReservationFromWaitlist(Reservation $reservation): bool
    {
        $event = $reservation->getEvent();
        if (!$event instanceof Event || $event->getId() === null) {
            return false;
        }

        $email = mb_strtolower(trim($reservation->getEmailParticipant()));
        if ($email === '') {
            return false;
        }

        $entry = $this->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.event = :event')
            ->andWhere('LOWER(w.email) = :email')
            ->andWhere('w.status = :status')
            ->andWhere('w.invitedAt IS NOT NULL')
            ->setParameter('event', $event)
            ->setParameter('email', $email)
            ->setParameter('status', WaitlistEntry::STATUS_CONFIRMEE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return is_array($entry);
    }
}
