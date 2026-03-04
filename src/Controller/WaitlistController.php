<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\WaitlistEntry;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventParticipantEmailResolver;
use App\Service\LoyaltyService;
use App\Service\WaitlistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WaitlistController extends AbstractController
{
    #[Route('/events/{id}/waitlist/join', name: 'app_waitlist_join_form', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function joinForm(
        Event $event,
        Request $request,
        EventParticipantEmailResolver $emailResolver,
        WaitlistEntryRepository $waitlistEntryRepository,
        WaitlistService $waitlistService
    ): Response {
        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        $existingEntry = null;
        $position = null;
        if ($participantEmail !== null) {
            $existingEntry = $waitlistEntryRepository->findOneByEventAndEmail($event, $participantEmail);
            if ($existingEntry instanceof WaitlistEntry) {
                $position = $waitlistService->computeQueuePosition($event, $participantEmail);
            }
        }

        return $this->render('waitlist/join.html.twig', [
            'event' => $event,
            'participantEmail' => $participantEmail,
            'existingEntry' => $existingEntry,
            'position' => $position,
        ]);
    }

    #[Route('/events/{id}/waitlist/join', name: 'app_waitlist_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(
        Event $event,
        Request $request,
        EventParticipantEmailResolver $emailResolver,
        WaitlistService $waitlistService
    ): Response {
        if (!$this->isCsrfTokenValid('waitlist_join_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('app_waitlist_join_form', ['id' => $event->getId()]);
        }

        $nom = trim((string) $request->request->get('nom'));
        $email = mb_strtolower(trim((string) $request->request->get('email')));

        if ($nom === '' || mb_strlen($nom) > 150) {
            $this->addFlash('error', 'Nom invalide (1 a 150 caracteres).');
            return $this->redirectToRoute('app_waitlist_join_form', ['id' => $event->getId()]);
        }
        if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_waitlist_join_form', ['id' => $event->getId()]);
        }

        try {
            $entry = $waitlistService->addToWaitlist($event, $email, $nom);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('events', ['event' => $event->getId()]);
        }

        $emailResolver->rememberEmail($request, $email);
        $position = $waitlistService->computeQueuePosition($event, $email);
        $this->addFlash('success', 'Vous etes sur la liste d attente.'.($position ? ' Votre rang: '.$position.'.' : ''));

        return $this->redirectToRoute('events', ['event' => $event->getId()]);
    }

    #[Route('/waitlist/confirm/{token}', name: 'app_waitlist_confirm_show', methods: ['GET'])]
    public function confirmShow(
        string $token,
        WaitlistEntryRepository $waitlistEntryRepository
    ): Response {
        $entry = $waitlistEntryRepository->findOneBy(['token' => trim($token)]);

        return $this->render('waitlist/confirm.html.twig', [
            'entry' => $entry,
            'token' => $token,
        ]);
    }

    #[Route('/waitlist/confirm/{token}', name: 'app_waitlist_confirm', methods: ['POST'])]
    public function confirm(
        string $token,
        Request $request,
        WaitlistService $waitlistService
    ): Response {
        if (!$this->isCsrfTokenValid('waitlist_confirm_'.$token, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('app_waitlist_confirm_show', ['token' => $token]);
        }

        try {
            $reservation = $waitlistService->confirmInvite($token);
            $this->addFlash('success', 'Reservation confirmee depuis la liste d attente.');
            return $this->redirectToRoute('app_reservation_success', ['id' => $reservation->getId()]);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('events');
        }
    }
}
