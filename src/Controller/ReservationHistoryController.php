<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\WaitlistEntry;
use App\Repository\ReservationRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventCapacityService;
use App\Service\EventParticipantEmailResolver;
use App\Service\ReservationQrService;
use App\Service\WaitlistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationHistoryController extends AbstractController
{
    private const CANCELLABLE_STATUSES = [
        Reservation::STATUS_CONFIRMED,
        'EN_ATTENTE_PAIEMENT',
        'PAYEE',
        'Payee',
    ];

    #[Route('/events/history', name: 'app_reservation_history_index', methods: ['GET'])]
    public function index(
        Request $request,
        ReservationRepository $reservationRepository,
        EventParticipantEmailResolver $emailResolver,
        WaitlistEntryRepository $waitlistEntryRepository
    ): Response {
        $email = $this->resolveParticipantEmail($request, $emailResolver);

        if ($email === null) {
            $this->addFlash('error', 'Impossible d identifier votre email participant.');

            return $this->render('reservation/history.html.twig', [
                'reservations' => [],
                'waitlistInvites' => [],
                'waitlistEntries' => [],
                'cancelableReservationIds' => [],
                'participantEmail' => null,
            ]);
        }

        $reservations = $reservationRepository->findHistoryByEmail($email, 100);
        $reservations = array_values(array_filter(
            $reservations,
            static fn (Reservation $reservation): bool => $reservation->getStatut() !== Reservation::STATUS_CANCELLED
        ));
        $waitlistInvites = $waitlistEntryRepository->findInvitesForEmail($email, 20);
        $waitlistEntries = $waitlistEntryRepository->findByEmail($email, 50);
        $cancelableReservationIds = $this->resolveCancelableReservationIds($reservations);

        return $this->render('reservation/history.html.twig', [
            'reservations' => $reservations,
            'waitlistInvites' => $waitlistInvites,
            'waitlistEntries' => $waitlistEntries,
            'cancelableReservationIds' => $cancelableReservationIds,
            'participantEmail' => $email,
        ]);
    }

    #[Route('/events/history/{id}', name: 'app_reservation_history_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        Request $request,
        ReservationRepository $reservationRepository,
        EventParticipantEmailResolver $emailResolver,
        ReservationQrService $qrService
    ): Response {
        $email = $this->resolveParticipantEmail($request, $emailResolver);
        if ($email === null) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        $reservation = $reservationRepository->findOneForEmail($id, $email);
        if (!$reservation instanceof Reservation) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        $validationUrl = null;
        $qrImageUrl = null;
        if ($reservation->getQrToken() !== null && $reservation->getQrToken() !== '') {
            $validationUrl = $qrService->buildValidationUrl($reservation);
            $qrImageUrl = $qrService->buildQrImageUrl($validationUrl);
        }

        $canGenerateQr = false;
        $user = $this->getUser();
        if (is_object($user) && method_exists($user, 'getEmail')) {
            $userEmail = $this->sanitizeEmail((string) $user->getEmail());
            if ($userEmail !== null && $userEmail === mb_strtolower($reservation->getEmailParticipant())) {
                $canGenerateQr = true;
            }
        }

        return $this->render('reservation/history_show.html.twig', [
            'reservation' => $reservation,
            'event' => $reservation->getEvent(),
            'validationUrl' => $validationUrl,
            'qrImageUrl' => $qrImageUrl,
            'canGenerateQr' => $canGenerateQr,
        ]);
    }

    #[Route('/reservations/{id}/cancel', name: 'app_reservation_history_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(
        int $id,
        Request $request,
        ReservationRepository $reservationRepository,
        EventParticipantEmailResolver $emailResolver,
        EntityManagerInterface $em,
        WaitlistService $waitlistService,
        EventCapacityService $eventCapacityService
    ): Response {
        $email = $this->resolveParticipantEmail($request, $emailResolver);
        if ($email === null) {
            $this->addFlash('error', 'Impossible d identifier votre email participant.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        if (!$this->isCsrfTokenValid('reservation_cancel_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        $reservation = $reservationRepository->findOneForEmail($id, $email);
        if (!$reservation instanceof Reservation) {
            $this->addFlash('error', 'Reservation introuvable.');
            return $this->redirectToRoute('app_reservation_history_index');
        }
        $event = $reservation->getEvent();
        if (!$this->canCancelReservation($reservation)) {
            $this->addFlash('error', 'Cette reservation ne peut plus etre annulee.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        $reservation->setStatut(Reservation::STATUS_CANCELLED);
        $reservation->setTransactionId(null);
        $em->flush();

        if ($event !== null && $eventCapacityService->remainingPlaces($event) > 0) {
            $promotedReservation = $waitlistService->promoteNext($event);
            if ($promotedReservation instanceof Reservation) {
                $this->addFlash('info', 'Promotion automatique FIFO: un participant de la liste d attente a ete confirme.');
            }
        }

        $this->addFlash('success', 'Reservation annulee. Une place s est liberee.');
        return $this->redirectToRoute('app_reservation_history_index');
    }

    #[Route('/waitlist/{id}/leave', name: 'app_waitlist_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leaveWaitlist(
        int $id,
        Request $request,
        WaitlistEntryRepository $waitlistEntryRepository,
        EventParticipantEmailResolver $emailResolver,
        EntityManagerInterface $em
    ): Response {
        $email = $this->resolveParticipantEmail($request, $emailResolver);
        if ($email === null) {
            $this->addFlash('error', 'Impossible d identifier votre email participant.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        if (!$this->isCsrfTokenValid('waitlist_leave_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        $entry = $waitlistEntryRepository->find($id);
        if (!$entry instanceof WaitlistEntry || mb_strtolower($entry->getEmail()) !== $email) {
            $this->addFlash('error', 'Entree waitlist introuvable.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        if (!in_array($entry->getStatus(), [WaitlistEntry::STATUS_EN_ATTENTE, WaitlistEntry::STATUS_INVITE], true)) {
            $this->addFlash('error', 'Cette entree waitlist ne peut pas etre modifiee.');
            return $this->redirectToRoute('app_reservation_history_index');
        }

        $entry->setStatus(WaitlistEntry::STATUS_ANNULEE);
        $entry->setToken(null);
        $entry->setInvitedAt(null);
        $entry->setExpiresAt(null);
        $em->flush();

        $this->addFlash('success', 'Vous avez quitte la liste d attente.');
        return $this->redirectToRoute('app_reservation_history_index');
    }

    private function resolveParticipantEmail(Request $request, EventParticipantEmailResolver $emailResolver): ?string
    {
        $user = $this->getUser();
        if (is_object($user) && method_exists($user, 'getEmail')) {
            $email = $this->sanitizeEmail((string) $user->getEmail());
            if ($email !== null) {
                return $email;
            }
        }

        $resolverEmail = $emailResolver->resolve($request, $user);
        if ($resolverEmail !== null) {
            return $resolverEmail;
        }

        if ($request->hasSession()) {
            $sessionEmail = $request->getSession()->get('email_participant');
            if (is_string($sessionEmail)) {
                return $this->sanitizeEmail($sessionEmail);
            }
        }

        return null;
    }

    private function sanitizeEmail(string $email): ?string
    {
        $email = mb_strtolower(trim($email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * @param Reservation[] $reservations
     * @return int[]
     */
    private function resolveCancelableReservationIds(array $reservations): array
    {
        $ids = [];
        foreach ($reservations as $reservation) {
            if ($reservation instanceof Reservation && $this->canCancelReservation($reservation) && $reservation->getId() !== null) {
                $ids[] = (int) $reservation->getId();
            }
        }

        return $ids;
    }

    private function canCancelReservation(Reservation $reservation): bool
    {
        if (!in_array($reservation->getStatut(), self::CANCELLABLE_STATUSES, true)) {
            return false;
        }
        $event = $reservation->getEvent();
        if ($event === null || $event->getDateEvent() === null) {
            return false;
        }

        return $event->getDateEvent() >= new \DateTimeImmutable('today');
    }
}
