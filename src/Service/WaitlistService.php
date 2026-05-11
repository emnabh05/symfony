<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\Reservation;
use App\Entity\WaitlistEntry;
use App\Repository\ReservationRepository;
use App\Repository\WaitlistEntryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class WaitlistService
{
    public const INVITE_TTL_MINUTES = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WaitlistEntryRepository $waitlistEntryRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EventCapacityService $eventCapacityService,
    ) {
    }

    public function joinWaitlist(Event $event, string $nom, string $email): WaitlistEntry
    {
        $nom = trim($nom);
        $email = mb_strtolower(trim($email));

        if (!$this->eventCapacityService->isFull($event)) {
            throw new \DomainException('Il reste des places, utilisez la reservation normale.');
        }

        if ($this->hasActiveReservationForEvent($event, $email)) {
            throw new \DomainException('Vous avez deja une reservation active pour cet evenement.');
        }

        $existing = $this->waitlistEntryRepository->findOneByEventAndEmail($event, $email);
        if ($existing instanceof WaitlistEntry) {
            throw new \DomainException('Vous etes deja inscrit sur la liste d attente pour cet evenement.');
        }

        $entry = new WaitlistEntry();
        $entry->setEvent($event);
        $entry->setNom($nom);
        $entry->setEmail($email);
        $entry->setStatus(WaitlistEntry::STATUS_EN_ATTENTE);
        $entry->setPosition(0);
        $entry->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($entry);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new \DomainException('Vous etes deja inscrit sur la liste d attente pour cet evenement.');
        }

        $this->refreshPendingPositions($event);

        return $entry;
    }

    public function addToWaitlist(Event $event, string $email, string $nom): WaitlistEntry
    {
        return $this->joinWaitlist($event, $nom, $email);
    }

    public function processNextInvite(Event $event): ?WaitlistEntry
    {
        $this->expireInvites();

        $availableSlots = $this->eventCapacityService->remainingPlaces($event) - $this->waitlistEntryRepository->countActiveInvitesForEvent($event);
        if ($availableSlots <= 0) {
            return null;
        }

        $next = $this->waitlistEntryRepository->findNextPendingForEvent($event);
        if (!$next instanceof WaitlistEntry) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $next->setStatus(WaitlistEntry::STATUS_INVITE);
        $next->setToken($this->generateUniqueToken());
        $next->setInvitedAt($now);
        $next->setExpiresAt($now->modify('+'.self::INVITE_TTL_MINUTES.' minutes'));
        $this->em->flush();

        return $next;
    }

    public function promoteNext(Event $event): ?Reservation
    {
        if (!$this->eventCapacityService->isFull($event) && $this->eventCapacityService->remainingPlaces($event) <= 0) {
            return null;
        }

        $next = $this->waitlistEntryRepository->findNextForEvent($event);
        if (!$next instanceof WaitlistEntry) {
            return null;
        }

        $email = mb_strtolower(trim($next->getEmail()));
        if ($email === '') {
            $next->setStatus(WaitlistEntry::STATUS_ANNULEE);
            $this->em->flush();
            return null;
        }

        if ($this->hasActiveReservationForEvent($event, $email)) {
            $next->setStatus(WaitlistEntry::STATUS_ANNULEE);
            $this->em->flush();
            return $this->promoteNext($event);
        }

        if ($this->eventCapacityService->remainingPlaces($event) <= 0) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setNomParticipant($next->getNom());
        $reservation->setEmailParticipant($email);
        $reservation->setDateReservation($now);
        $reservation->setMontant($event->getPrixEvent() ?? '0.00');
        $reservation->setStatut(Reservation::STATUS_CONFIRMED);

        $next->setStatus(WaitlistEntry::STATUS_CONFIRMEE);
        $next->setInvitedAt($now);
        $next->setToken(null);
        $next->setExpiresAt(null);

        $this->em->persist($reservation);

        $existingParticipation = (int) $this->em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('IDENTITY(p.event) = :eventId')
            ->andWhere('LOWER(p.emailParticipant) = :email')
            ->setParameter('eventId', (int) $event->getId())
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();

        if ($existingParticipation === 0) {
            $participation = new Participation();
            $participation->setEvent($event);
            $participation->setNomParticipant($next->getNom());
            $participation->setEmailParticipant($email);
            $participation->setDateInscription($now);
            $this->em->persist($participation);
        }

        $this->em->flush();
        $this->refreshPendingPositions($event);

        return $reservation;
    }

    public function isEventFull(Event $event): bool
    {
        return $this->eventCapacityService->isFull($event);
    }

    public function expireInvites(): int
    {
        $expired = $this->waitlistEntryRepository->findExpiredInvites(new \DateTimeImmutable());
        if ($expired === []) {
            return 0;
        }

        $eventsToReprocess = [];
        foreach ($expired as $entry) {
            $entry->setStatus(WaitlistEntry::STATUS_EXPIREE);
            $entry->setToken(null);
            $event = $entry->getEvent();
            if ($event instanceof Event && $event->getId() !== null) {
                $eventsToReprocess[$event->getId()] = $event;
            }
        }
        $this->em->flush();

        foreach ($eventsToReprocess as $event) {
            $this->processNextInvite($event);
        }

        return count($expired);
    }

    public function expireInvitesForEvent(Event $event): int
    {
        $expired = $this->waitlistEntryRepository->findExpiredInvitesForEvent($event, new \DateTimeImmutable());
        if ($expired === []) {
            return 0;
        }

        foreach ($expired as $entry) {
            $entry->setStatus(WaitlistEntry::STATUS_EXPIREE);
            $entry->setToken(null);
            $entry->setInvitedAt(null);
            $entry->setExpiresAt(null);
        }
        $this->em->flush();
        $this->refreshPendingPositions($event);
        $this->processNextInvite($event);

        return count($expired);
    }

    public function cancelEntry(WaitlistEntry $entry): void
    {
        $event = $entry->getEvent();
        if (!$event instanceof Event) {
            throw new \DomainException('Evenement introuvable pour cette entree waitlist.');
        }

        $previousStatus = $entry->getStatus();
        $entry->setStatus(WaitlistEntry::STATUS_ANNULEE);
        $entry->setToken(null);
        $entry->setInvitedAt(null);
        $entry->setExpiresAt(null);
        $this->em->flush();

        $this->refreshPendingPositions($event);

        if ($previousStatus === WaitlistEntry::STATUS_INVITE) {
            $this->processNextInvite($event);
        }
    }

    public function confirmInvite(string $token): Reservation
    {
        $token = trim($token);
        if ($token === '') {
            throw new \DomainException('Token invalide.');
        }

        $entry = $this->waitlistEntryRepository->findOneBy(['token' => $token]);
        if (!$entry instanceof WaitlistEntry) {
            throw new \DomainException('Invitation introuvable ou invalide.');
        }
        if ($entry->getStatus() !== WaitlistEntry::STATUS_INVITE) {
            throw new \DomainException('Cette invitation n est plus active.');
        }
        $expiresAt = $entry->getExpiresAt();
        if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt < new \DateTimeImmutable()) {
            $entry->setStatus(WaitlistEntry::STATUS_EXPIREE);
            $entry->setToken(null);
            $this->em->flush();
            throw new \DomainException('Cette invitation a expire.');
        }

        $event = $entry->getEvent();
        if (!$event instanceof Event) {
            throw new \DomainException('Evenement introuvable.');
        }

        if ($this->eventCapacityService->isFull($event)) {
            $entry->setStatus(WaitlistEntry::STATUS_EN_ATTENTE);
            $entry->setToken(null);
            $entry->setInvitedAt(null);
            $entry->setExpiresAt(null);
            $this->em->flush();
            throw new \DomainException('Plus de place disponible pour le moment. Vous restez en attente.');
        }

        $now = new \DateTimeImmutable();
        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setNomParticipant($entry->getNom());
        $reservation->setEmailParticipant($entry->getEmail());
        $reservation->setDateReservation($now);
        $reservation->setMontant($event->getPrixEvent() ?? '0.00');
        $reservation->setStatut(Reservation::STATUS_CONFIRMED);

        $entry->setStatus(WaitlistEntry::STATUS_CONFIRMEE);
        $entry->setToken(null);

        $this->em->persist($reservation);
        $existingParticipation = (int) $this->em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('IDENTITY(p.event) = :eventId')
            ->andWhere('LOWER(p.emailParticipant) = :email')
            ->setParameter('eventId', (int) $event->getId())
            ->setParameter('email', mb_strtolower($entry->getEmail()))
            ->getQuery()
            ->getSingleScalarResult();

        if ($existingParticipation === 0) {
            $participation = new Participation();
            $participation->setEvent($event);
            $participation->setNomParticipant($entry->getNom());
            $participation->setEmailParticipant($entry->getEmail());
            $participation->setDateInscription($now);
            $this->em->persist($participation);
        }

        $this->em->flush();
        $this->processNextInvite($event);

        return $reservation;
    }

    public function computeQueuePosition(Event $event, string $email): ?int
    {
        $entry = $this->waitlistEntryRepository->findOneByEventAndEmail($event, $email);
        if (!$entry instanceof WaitlistEntry || $entry->getStatus() !== WaitlistEntry::STATUS_EN_ATTENTE || $entry->getId() === null) {
            return null;
        }

        return 1 + $this->waitlistEntryRepository->countPendingBefore($event, $entry->getCreatedAt(), (int) $entry->getId());
    }

    private function refreshPendingPositions(Event $event): void
    {
        $pending = $this->em->getRepository(WaitlistEntry::class)
            ->createQueryBuilder('w')
            ->andWhere('w.event = :event')
            ->andWhere('w.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', WaitlistEntry::STATUS_EN_ATTENTE)
            ->orderBy('w.createdAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();

        $position = 1;
        foreach ($pending as $entry) {
            $entry->setPosition($position++);
        }
        $this->em->flush();
    }

    private function generateUniqueToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
            $existing = $this->waitlistEntryRepository->findOneBy(['token' => $token]);
        } while ($existing instanceof WaitlistEntry);

        return $token;
    }

    private function hasActiveReservationForEvent(Event $event, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        $reservations = $this->reservationRepository->findLatestByEmail($email, 200);
        foreach ($reservations as $reservation) {
            $reservedEvent = $reservation->getEvent();
            if (!$reservedEvent instanceof Event || $reservedEvent->getId() !== $event->getId()) {
                continue;
            }
            if ($reservation->getStatut() !== Reservation::STATUS_CANCELLED) {
                return true;
            }
        }

        return false;
    }
}
