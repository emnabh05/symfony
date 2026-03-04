<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Favorite;
use App\Repository\EventReviewRepository;
use App\Repository\FavoriteRepository;
use App\Service\EventParticipantEmailResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FavoriteController extends AbstractController
{
    #[Route('/events/{id}/favorite', name: 'app_event_favorite_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        FavoriteRepository $favoriteRepository,
        EventParticipantEmailResolver $emailResolver
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('event_favorite_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectBack($request, $id);
        }

        $email = $emailResolver->resolve($request, $this->getUser());
        if ($email === null) {
            $this->addFlash('error', 'Veuillez vous connecter ou reserver un evenement pour activer les favoris.');
            return $this->redirectBack($request, $id);
        }

        $event = $em->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'Evenement introuvable.');
            return $this->redirectToRoute('events');
        }

        $existing = $favoriteRepository->findOneBy([
            'event' => $event,
            'emailParticipant' => $email,
        ]);

        if ($existing instanceof Favorite) {
            $em->remove($existing);
            $this->addFlash('success', 'Evenement retire de vos favoris.');
        } else {
            $favorite = new Favorite();
            $favorite->setEvent($event);
            $favorite->setEmailParticipant($email);
            $favorite->setCreatedAt(new \DateTimeImmutable());
            $em->persist($favorite);
            $this->addFlash('success', 'Evenement ajoute a vos favoris.');
        }

        $em->flush();
        return $this->redirectBack($request, $id);
    }

    #[Route('/events/favorites', name: 'app_event_favorites', methods: ['GET'])]
    public function myFavorites(
        Request $request,
        FavoriteRepository $favoriteRepository,
        EventReviewRepository $eventReviewRepository,
        EventParticipantEmailResolver $emailResolver
    ): Response {
        $email = $emailResolver->resolve($request, $this->getUser());
        if ($email === null) {
            $this->addFlash('error', 'Aucun email participant detecte. Faites d abord une reservation.');
            return $this->redirectToRoute('events');
        }

        $events = $favoriteRepository->findEventsFavoritedByEmail($email);
        $eventIds = array_values(array_filter(array_map(
            static fn (Event $event): ?int => $event->getId(),
            $events
        )));

        return $this->render('events/favorites.html.twig', [
            'events' => $events,
            'participantEmail' => $email,
            'favoriteCounts' => $favoriteRepository->getCountsForEventIds($eventIds),
            'reviewStats' => $eventReviewRepository->getStatsForEvents($eventIds),
        ]);
    }

    private function redirectBack(Request $request, int $eventId): RedirectResponse
    {
        $url = trim((string) $request->request->get('redirect_url', ''));
        if ($url !== '') {
            return $this->redirect($url);
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('events', ['event' => $eventId]);
    }
}
