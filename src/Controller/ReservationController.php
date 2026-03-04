<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\Reservation;
use App\Form\EventReservationType;
use App\Repository\ReservationRepository;
use App\Service\EventCapacityService;
use App\Service\EventParticipantEmailResolver;
use App\Service\LoyaltyService;
use App\Service\ReservationQrService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    #[Route('/events/{id}/reservation', name: 'app_event_reservation_new', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventCapacityService $eventCapacityService,
        EventParticipantEmailResolver $emailResolver,
        LoyaltyService $loyaltyService
    ): Response {
        if (!$this->reservationTablesExist($em)) {
            $this->addFlash('error', 'Tables de reservation manquantes. Lancez les migrations.');
            return $this->redirectToRoute('events');
        }

        $event = $em->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'Evenement introuvable.');
            return $this->redirectToRoute('events');
        }

        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        if ($this->isPremiumEvent($event)) {
            if (!$this->isVipParticipant($participantEmail, $loyaltyService)) {
                $this->addFlash('error', 'Acces VIP requis.');
                return $this->redirectToRoute('events', ['event' => $event->getId()]);
            }
            $this->addFlash('info', 'Tu es VIP : acces premium autorise.');
        }

        $reservation = new Reservation();
        if ($participantEmail !== null) {
            $reservation->setEmailParticipant($participantEmail);
        }
        $form = $this->createForm(EventReservationType::class, $reservation, [
            'csrf_token_id' => 'events_reserve_'.$id,
        ]);

        return $this->render('reservation/new.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'isFull' => $eventCapacityService->isFull($event),
        ]);
    }

    #[Route('/events/{id}/reservation', name: 'app_event_reservation_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventCapacityService $eventCapacityService,
        EventParticipantEmailResolver $emailResolver,
        LoyaltyService $loyaltyService
    ): Response
    {
        if (!$this->reservationTablesExist($em)) {
            $this->addFlash('error', 'Tables de reservation manquantes. Lancez les migrations.');
            return $this->redirectToRoute('events');
        }

        $event = $em->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'Evenement introuvable.');
            return $this->redirectToRoute('events');
        }

        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        if ($this->isPremiumEvent($event) && !$this->isVipParticipant($participantEmail, $loyaltyService)) {
            $this->addFlash('error', 'Acces VIP requis.');
            return $this->redirectToRoute('events', ['event' => $event->getId()]);
        }

        $reservation = new Reservation();
        if ($participantEmail !== null) {
            $reservation->setEmailParticipant($participantEmail);
        }

        $form = $this->createForm(EventReservationType::class, $reservation, [
            'csrf_token_id' => 'events_reserve_'.$id,
        ]);
        $form->handleRequest($request);

        if ($eventCapacityService->isFull($event)) {
            $this->addFlash('error', 'Cet evenement est complet.');

            return $this->render('reservation/new.html.twig', [
                'event' => $event,
                'form' => $form->createView(),
                'isFull' => true,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $nom = trim($reservation->getNomParticipant());
            $prenom = trim((string) $form->get('prenomParticipant')->getData());
            $nomComplet = trim($nom.' '.$prenom);
            $reservation->setNomParticipant($nomComplet);
            $email = trim($reservation->getEmailParticipant());

            if ($this->isPremiumEvent($event) && !$this->isVipParticipant($email, $loyaltyService)) {
                $this->addFlash('error', 'Acces VIP requis.');
                return $this->redirectToRoute('events', ['event' => $event->getId()]);
            }

            $now = new \DateTimeImmutable();

            $reservation->setEvent($event);
            $reservation->setDateReservation($now);
            $price = $event->getPrixEvent();
            $reservation->setMontant($price !== null ? $price : '0.00');
            $reservation->setStatut(Reservation::STATUS_CONFIRMED);
            $reservation->setQrToken(null);
            $reservation->setQrGeneratedAt(null);

            $em->persist($reservation);
            $existingParticipation = (int) $em->getRepository(Participation::class)
                ->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('IDENTITY(p.event) = :eventId')
                ->andWhere('LOWER(p.emailParticipant) = :email')
                ->setParameter('eventId', (int) $event->getId())
                ->setParameter('email', mb_strtolower($email))
                ->getQuery()
                ->getSingleScalarResult();

            if ($existingParticipation === 0) {
                $participation = new Participation();
                $participation->setEvent($event);
                $participation->setNomParticipant($reservation->getNomParticipant());
                $participation->setEmailParticipant($email);
                $participation->setDateInscription($now);
                $em->persist($participation);
            }

            $em->flush();
            $emailResolver->rememberEmail($request, $email);
            $this->addFlash('success', 'Reservation confirmee avec succes.');

            return $this->redirectToRoute('app_reservation_success', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/new.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'isFull' => $eventCapacityService->isFull($event),
        ]);
    }

    #[Route('/reservation/success/{id}', name: 'app_reservation_success', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function success(Reservation $reservation): Response
    {
        return $this->render('reservation/success.html.twig', [
            'reservation' => $reservation,
            'event' => $reservation->getEvent(),
        ]);
    }

    #[Route('/reservations/{id}/qr', name: 'app_reservation_qr_generate', methods: ['GET'])]
    public function generateQr(
        Reservation $reservation,
        EntityManagerInterface $em,
        ReservationQrService $qrService
    ): Response {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getEmail')) {
            return $this->redirectToRoute('app_login');
        }

        if (mb_strtolower($reservation->getEmailParticipant()) !== mb_strtolower((string) $user->getEmail())) {
            $this->addFlash('error', 'Acces non autorise a cette reservation.');
            return $this->redirectToRoute('app_profile');
        }

        if (!in_array($reservation->getStatut(), [Reservation::STATUS_CONFIRMED, Reservation::STATUS_USED], true)) {
            $this->addFlash('error', 'Le QR code n est pas disponible pour cette reservation.');
            return $this->redirectToRoute('app_reservation_history_show', ['id' => $reservation->getId()]);
        }

        if (empty($reservation->getQrToken())) {
            $reservation->setQrToken($this->generateUniqueQrToken($em));
        }
        if ($reservation->getQrGeneratedAt() === null) {
            $reservation->setQrGeneratedAt(new \DateTimeImmutable());
        }
        $em->flush();

        $validationUrl = $qrService->buildValidationUrl($reservation);
        $qrImageUrl = $qrService->buildQrImageUrl($validationUrl);

        return $this->render('reservation/qr_code.html.twig', [
            'reservation' => $reservation,
            'event' => $reservation->getEvent(),
            'validationUrl' => $validationUrl,
            'qrImageUrl' => $qrImageUrl,
        ]);
    }

    #[Route('/r/{token}', name: 'app_reservation_qr_validate', methods: ['GET'])]
    public function validateQr(
        string $token,
        ReservationRepository $reservationRepository
    ): Response {
        $reservation = $reservationRepository->findByQrToken($token);
        if (!$reservation instanceof Reservation) {
            return $this->render('reservation/qr_validation.html.twig', [
                'isValid' => false,
                'errorMessage' => 'QR invalide',
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        if ($reservation->getStatut() !== Reservation::STATUS_CONFIRMED) {
            return $this->render('reservation/qr_validation.html.twig', [
                'isValid' => false,
                'errorMessage' => 'QR invalide',
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        $event = $reservation->getEvent();
        if (!$event instanceof Event) {
            return $this->render('reservation/qr_validation.html.twig', [
                'isValid' => false,
                'errorMessage' => 'QR invalide',
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        return $this->render('reservation/qr_validation.html.twig', [
            'isValid' => true,
            'reservation' => $reservation,
            'event' => $event,
            'statusLabel' => 'Réservation confirmée',
        ]);
    }

    private function reservationTablesExist(EntityManagerInterface $em): bool
    {
        try {
            $tables = $em->getConnection()->createSchemaManager()->listTableNames();
            return in_array('events', $tables, true)
                && in_array('participation', $tables, true)
                && in_array('reservation', $tables, true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function generateUniqueQrToken(EntityManagerInterface $em): string
    {
        do {
            $token = bin2hex(random_bytes(32));
            $existing = $em->getRepository(Reservation::class)->findOneBy(['qrToken' => $token]);
        } while ($existing instanceof Reservation);

        return $token;
    }

    private function isPremiumEvent(Event $event): bool
    {
        return $event->isPremium();
    }

    private function isVipParticipant(?string $email, LoyaltyService $loyaltyService): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return $loyaltyService->isVipEmail($email);
    }
}
