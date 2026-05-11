<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\Reservation;
use App\Repository\EventReviewRepository;
use App\Repository\FavoriteRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventCapacityService;
use App\Service\EventParticipantEmailResolver;
use App\Service\LoyaltyService;
use App\Service\WaitlistService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events/{id}', name: 'app_event_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Event $event,
        Request $request,
        FavoriteRepository $favoriteRepository,
        EventReviewRepository $eventReviewRepository,
        EventCapacityService $eventCapacityService,
        LoyaltyService $loyaltyService,
        WaitlistEntryRepository $waitlistEntryRepository,
        WaitlistService $waitlistService,
        EventParticipantEmailResolver $emailResolver,
        EntityManagerInterface $em
    ): Response {
        $eventId = (int) $event->getId();
        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        $sort = (string) $request->query->get('reviews_sort', 'recent');
        if (!in_array($sort, ['recent', 'best'], true)) {
            $sort = 'recent';
        }

        $reviewStats = $eventReviewRepository->getStatsForEvent($eventId);
        $average = (float) $reviewStats['average'];
        $reviewCount = (int) $reviewStats['count'];
        $favoriteCount = $favoriteRepository->countByEvent($eventId);
        $reviews = $eventReviewRepository->findByEvent($eventId, $sort);

        $isFavorited = false;
        $myReview = null;
        $canReview = false;
        $canAccessVipEvents = false;
        $vipReservationThreshold = $loyaltyService->getVipReservationThreshold();
        $waitlistEntry = null;
        $waitlistPosition = null;
        $isFull = $eventCapacityService->isFull($event);
        $remainingPlaces = $eventCapacityService->remainingPlaces($event);
        if ($participantEmail !== null) {
            $isFavorited = $favoriteRepository->isFavorited($participantEmail, $eventId);
            $myReview = $eventReviewRepository->findOneByEmailAndEvent($participantEmail, $eventId);
            $canReview = $this->hasParticipated($em, $eventId, $participantEmail);
            $canAccessVipEvents = $loyaltyService->isVipEmail($participantEmail);
            $waitlistEntry = $waitlistEntryRepository->findOneByEventAndEmail($event, $participantEmail);
            $waitlistPosition = $waitlistService->computeQueuePosition($event, $participantEmail);
        }

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'participantEmail' => $participantEmail,
            'canAccessVipEvents' => $canAccessVipEvents,
            'vipReservationThreshold' => $vipReservationThreshold,
            'waitlistEntry' => $waitlistEntry,
            'waitlistPosition' => $waitlistPosition,
            'isFull' => $isFull,
            'remainingPlaces' => $remainingPlaces,
            'isFavorited' => $isFavorited,
            'favoriteCount' => $favoriteCount,
            'reviews' => $reviews,
            'reviewsSort' => $sort,
            'reviewStats' => $reviewStats,
            'average' => $average,
            'reviewCount' => $reviewCount,
            'myReview' => $myReview,
            'canReview' => $canReview,
        ]);
    }

    private function hasParticipated(EntityManagerInterface $em, int $eventId, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        $participationCount = (int) $em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('IDENTITY(p.event) = :eventId')
            ->andWhere('LOWER(p.emailParticipant) = :email')
            ->setParameter('eventId', $eventId)
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
        if ($participationCount > 0) {
            return true;
        }

        $reservationCount = (int) $em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('IDENTITY(r.event) = :eventId')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->andWhere('r.statut IN (:allowed)')
            ->setParameter('eventId', $eventId)
            ->setParameter('email', $email)
            ->setParameter('allowed', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_USED])
            ->getQuery()
            ->getSingleScalarResult();

        return $reservationCount > 0;
    }

}
