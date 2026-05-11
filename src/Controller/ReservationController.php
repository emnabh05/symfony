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
use Symfony\Component\Form\FormError;
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
        $user = $this->getUser();
        if (\is_object($user) && \method_exists($user, 'getPhone')) {
            $userPhone = $this->normalizePhone((string) $user->getPhone());
            if ($userPhone !== null) {
                $reservation->setTelephoneParticipant($userPhone);
            }
        }

        $form = $this->createForm(EventReservationType::class, $reservation, [
            'csrf_token_id' => 'events_reserve_'.$id,
        ]);

        return $this->render('reservation/new.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'isFull' => $eventCapacityService->isFull($event),
            'requiresPayment' => ((float) $event->getPrixEvent()) > 0,
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
                'requiresPayment' => ((float) $event->getPrixEvent()) > 0,
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $nom = trim($reservation->getNomParticipant());
            $prenom = trim((string) $form->get('prenomParticipant')->getData());
            $nomComplet = trim($nom.' '.$prenom);
            $reservation->setNomParticipant($nomComplet);
            $email = trim($reservation->getEmailParticipant());
            $telephone = $this->normalizePhone((string) $reservation->getTelephoneParticipant());

            if ($telephone === null) {
                $form->get('telephoneParticipant')->addError(new FormError('Le telephone doit etre au format +216XXXXXXXX, 216XXXXXXXX ou XXXXXXXX.'));

                return $this->render('reservation/new.html.twig', [
                    'event' => $event,
                    'form' => $form->createView(),
                    'isFull' => $eventCapacityService->isFull($event),
                    'requiresPayment' => ((float) $event->getPrixEvent()) > 0,
                ]);
            }
            $reservation->setTelephoneParticipant($telephone);

            if ($this->isPremiumEvent($event) && !$this->isVipParticipant($email, $loyaltyService)) {
                $this->addFlash('error', 'Acces VIP requis.');
                return $this->redirectToRoute('events', ['event' => $event->getId()]);
            }

            $now = new \DateTimeImmutable();
            $price = $event->getPrixEvent();
            $requiresPayment = ((float) $price) > 0;

            $reservation->setEvent($event);
            $reservation->setDateReservation($now);
            $reservation->setMontant($price);
            $reservation->setStatut($requiresPayment ? Reservation::STATUS_PENDING_PAYMENT : Reservation::STATUS_CONFIRMED);
            $reservation->setQrToken(null);
            $reservation->setQrGeneratedAt(null);
            $reservation->setTransactionId(null);

            $em->persist($reservation);
            if (!$requiresPayment) {
                $this->addParticipationIfMissing($em, $reservation, $now);
            }

            $em->flush();
            $emailResolver->rememberEmail($request, $email);

            if ($requiresPayment) {
                $this->addFlash('success', 'Reservation creee en attente de paiement.');
                return $this->redirectToRoute('app_reservation_payment_show', ['id' => $reservation->getId()]);
            }

            $this->addFlash('success', 'Reservation confirmee avec succes.');
            return $this->redirectToRoute('app_reservation_success', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/new.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'isFull' => $eventCapacityService->isFull($event),
            'requiresPayment' => ((float) $event->getPrixEvent()) > 0,
        ]);
    }

    #[Route('/reservations/{id}/payment', name: 'app_reservation_payment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function paymentShow(
        Reservation $reservation,
        Request $request,
        EventParticipantEmailResolver $emailResolver
    ): Response {
        $event = $reservation->getEvent();
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        if (!$this->reservationBelongsToParticipant($reservation, $request, $emailResolver)) {
            $this->addFlash('error', 'Acces non autorise a ce paiement.');
            return $this->redirectToRoute('events');
        }

        if (!in_array($reservation->getStatut(), [Reservation::STATUS_PENDING_PAYMENT, Reservation::STATUS_PAID], true)) {
            return $this->redirectToRoute('app_reservation_success', ['id' => $reservation->getId()]);
        }

        return $this->render('reservation/payment.html.twig', [
            'reservation' => $reservation,
            'event' => $event,
        ]);
    }

    #[Route('/reservations/{id}/payment', name: 'app_reservation_payment_process', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function paymentProcess(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        EventParticipantEmailResolver $emailResolver
    ): Response {
        $event = $reservation->getEvent();
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Reservation introuvable.');
        }

        if (!$this->reservationBelongsToParticipant($reservation, $request, $emailResolver)) {
            $this->addFlash('error', 'Acces non autorise a ce paiement.');
            return $this->redirectToRoute('events');
        }

        if (!$this->isCsrfTokenValid('reservation_payment_'.$reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('app_reservation_payment_show', ['id' => $reservation->getId()]);
        }

        $action = trim((string) $request->request->get('payment_action', 'pay'));
        if ($action === 'cancel') {
            $this->addFlash('info', 'Paiement annule. Votre reservation reste en attente.');
            return $this->redirectToRoute('app_reservation_history_show', ['id' => $reservation->getId()]);
        }

        try {
            $this->validateSandboxPaymentPayload(
                trim((string) $request->request->get('payment_method', 'card')),
                trim((string) $request->request->get('card_holder', '')),
                trim((string) $request->request->get('card_number', '')),
                trim((string) $request->request->get('expiration', '')),
                trim((string) $request->request->get('cvv', ''))
            );
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_reservation_payment_show', ['id' => $reservation->getId()]);
        }

        if ($action === 'failed') {
            $reservation->setStatut(Reservation::STATUS_PENDING_PAYMENT);
            $reservation->setTransactionId(null);
            $em->flush();
            $this->addFlash('error', 'Paiement refuse. Corrigez les informations puis reessayez.');
            return $this->redirectToRoute('app_reservation_payment_show', ['id' => $reservation->getId()]);
        }

        $reservation->setStatut(Reservation::STATUS_PAID);
        $reservation->setTransactionId($this->generatePaymentTransactionId());
        $this->addParticipationIfMissing($em, $reservation, new \DateTimeImmutable());
        $em->flush();
        $emailResolver->rememberEmail($request, $reservation->getEmailParticipant());
        $this->addFlash('success', 'Paiement valide avec succes.');

        return $this->redirectToRoute('app_reservation_success', ['id' => $reservation->getId()]);
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

        if (!in_array($reservation->getStatut(), [Reservation::STATUS_CONFIRMED, Reservation::STATUS_USED, Reservation::STATUS_PAID], true)) {
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

        if (!in_array($reservation->getStatut(), [Reservation::STATUS_CONFIRMED, Reservation::STATUS_PAID], true)) {
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
            'statusLabel' => $reservation->getStatut() === Reservation::STATUS_PAID ? 'Reservation payee' : 'Reservation confirmee',
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

    private function addParticipationIfMissing(EntityManagerInterface $em, Reservation $reservation, \DateTimeImmutable $date): void
    {
        $event = $reservation->getEvent();
        if (!$event instanceof Event) {
            return;
        }

        $existingParticipation = (int) $em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('IDENTITY(p.event) = :eventId')
            ->andWhere('LOWER(p.emailParticipant) = :email')
            ->setParameter('eventId', (int) $event->getId())
            ->setParameter('email', mb_strtolower($reservation->getEmailParticipant()))
            ->getQuery()
            ->getSingleScalarResult();

        if ($existingParticipation > 0) {
            return;
        }

        $participation = new Participation();
        $participation->setEvent($event);
        $participation->setNomParticipant($reservation->getNomParticipant());
        $participation->setEmailParticipant($reservation->getEmailParticipant());
        if (method_exists($participation, 'setTelephoneParticipant')) {
            $participation->setTelephoneParticipant($reservation->getTelephoneParticipant());
        }
        $participation->setDateInscription($date);
        $em->persist($participation);
    }

    private function reservationBelongsToParticipant(Reservation $reservation, Request $request, EventParticipantEmailResolver $emailResolver): bool
    {
        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        if ($participantEmail === null) {
            return false;
        }

        return mb_strtolower(trim($reservation->getEmailParticipant())) === $participantEmail;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $normalized = preg_replace('/[\s\.-]+/', '', $phone) ?? '';
        if (preg_match('/^[0-9]{8}$/', $normalized)) {
            return '+216'.$normalized;
        }
        if (preg_match('/^216[0-9]{8}$/', $normalized)) {
            return '+'.$normalized;
        }
        if (preg_match('/^\+216[0-9]{8}$/', $normalized)) {
            return $normalized;
        }

        return null;
    }

    private function validateSandboxPaymentPayload(
        string $paymentMethod,
        string $cardHolder,
        string $cardNumber,
        string $expiration,
        string $cvv
    ): void {
        if (!in_array($paymentMethod, ['card', 'paypal', 'wallet'], true)) {
            throw new \InvalidArgumentException('Methode de paiement invalide.');
        }
        if ($cardHolder === '') {
            throw new \InvalidArgumentException('Le nom sur la carte est obligatoire.');
        }

        $digits = preg_replace('/\D+/', '', $cardNumber) ?? '';
        if (strlen($digits) !== 16) {
            throw new \InvalidArgumentException('Le numero de carte doit contenir 16 chiffres.');
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $expiration)) {
            throw new \InvalidArgumentException('La date d expiration doit etre au format MM/AA.');
        }
        if (!preg_match('/^[0-9]{3}$/', $cvv)) {
            throw new \InvalidArgumentException('Le CVV doit contenir 3 chiffres.');
        }
    }

    private function generatePaymentTransactionId(): string
    {
        return 'PAY-'.strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    }
}
