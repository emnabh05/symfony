<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\FavoriteRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

class EventRecommendationService
{
    private const SCORE_RESERVED_TYPE = 3;
    private const SCORE_FAVORITE_TYPE = 2;
    private const SCORE_VIP_PREMIUM = 2;
    private const SCORE_RECENT_EVENT = 1;
    private const RECENT_DAYS = 21;

    private const ACTIVE_RESERVATION_STATUSES = [
        Reservation::STATUS_CONFIRMED,
        Reservation::STATUS_USED,
        'EN_ATTENTE_PAIEMENT',
        'PAYEE',
        'Payee',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly FavoriteRepository $favoriteRepository,
        private readonly LoyaltyService $loyaltyService,
    ) {
    }

    /**
     * @return array<int, array{event: Event, score: int, reasons: string[]}>
     */
    public function getRecommendationsForUser(string $email): array
    {
        $email = $this->sanitizeEmail($email);
        if ($email === null) {
            return [];
        }

        $profile = $this->getUserPreferredTypes($email);
        $reservedTypeCounts = $profile['reservedTypeCounts'];
        $favoriteTypeCounts = $profile['favoriteTypeCounts'];
        $reservedEventIds = $profile['reservedEventIds'];
        $isVipUser = $profile['isVipUser'];

        if ($reservedTypeCounts === [] && $favoriteTypeCounts === []) {
            return [];
        }

        $candidatesQb = $this->em->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->select('e')
            ->addSelect('COUNT(r.id) AS HIDDEN activeReservations')
            ->leftJoin(
                Reservation::class,
                'r',
                'WITH',
                'r.event = e AND r.statut IN (:activeStatuses)'
            )
            ->where('e.dateEvent >= :today')
            ->setParameter('activeStatuses', self::ACTIVE_RESERVATION_STATUSES)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->groupBy('e.id')
            ->having('COUNT(r.id) < e.capacite')
            ->orderBy('e.dateEvent', 'ASC')
            ->setMaxResults(120);

        if ($reservedEventIds !== []) {
            $candidatesQb
                ->andWhere('e.id NOT IN (:reservedIds)')
                ->setParameter('reservedIds', $reservedEventIds);
        }

        /** @var Event[] $candidates */
        $candidates = $candidatesQb->getQuery()->getResult();
        $recentThreshold = (new \DateTimeImmutable('today'))->modify('-'.self::RECENT_DAYS.' days');

        $scored = [];
        foreach ($candidates as $event) {
            $type = mb_strtolower(trim($event->getTypeEvent()));
            $score = 0;
            $reasons = [];

            if (isset($reservedTypeCounts[$type])) {
                $score += self::SCORE_RESERVED_TYPE;
                $reasons[] = 'Type deja reserve';
            }

            if (isset($favoriteTypeCounts[$type])) {
                $score += self::SCORE_FAVORITE_TYPE;
                $reasons[] = 'Type favori';
            }

            if ($isVipUser && $event->isPremium()) {
                $score += self::SCORE_VIP_PREMIUM;
                $reasons[] = 'Acces VIP premium';
            }

            if ($event->getCreatedAt() >= $recentThreshold) {
                $score += self::SCORE_RECENT_EVENT;
                $reasons[] = 'Evenement recent';
            }

            $scored[] = ['event' => $event, 'score' => $score, 'reasons' => $reasons];
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                $scoreDiff = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }

                /** @var Event $eventA */
                $eventA = $a['event'];
                /** @var Event $eventB */
                $eventB = $b['event'];

                return $eventA->getDateEvent() <=> $eventB->getDateEvent();
            }
        );

        return array_slice($scored, 0, 5);
    }

    /**
     * Backward-compatible alias for existing calls.
     *
     * @return array<int, array{event: Event, score: int, reasons: string[]}>
     */
    public function recommendForEmail(?string $email, int $limit = 5): array
    {
        if ($email === null) {
            return [];
        }

        return array_slice($this->getRecommendationsForUser($email), 0, max(1, $limit));
    }

    /**
     * @return array{
     *   reservedTypeCounts: array<string, int>,
     *   favoriteTypeCounts: array<string, int>,
     *   reservedEventIds: int[],
     *   isVipUser: bool
     * }
     */
    private function getUserPreferredTypes(string $email): array
    {
        $reservedTypeCounts = [];
        $reservedEventIds = [];

        $reservations = $this->reservationRepository->findLatestByEmail($email, 300);
        foreach ($reservations as $reservation) {
            if ($reservation->getStatut() === Reservation::STATUS_CANCELLED) {
                continue;
            }
            $event = $reservation->getEvent();
            if (!$event instanceof Event || $event->getId() === null) {
                continue;
            }
            $reservedEventIds[(int) $event->getId()] = (int) $event->getId();
            $type = mb_strtolower(trim($event->getTypeEvent()));
            if ($type !== '') {
                $reservedTypeCounts[$type] = ($reservedTypeCounts[$type] ?? 0) + 1;
            }
        }

        $favoriteTypeCounts = [];
        $favorites = $this->favoriteRepository->findByEmail($email, 300);
        foreach ($favorites as $favorite) {
            if (!is_object($favorite) || !method_exists($favorite, 'getEvent')) {
                continue;
            }
            $event = $favorite->getEvent();
            if (!$event instanceof Event) {
                continue;
            }
            $type = mb_strtolower(trim($event->getTypeEvent()));
            if ($type !== '') {
                $favoriteTypeCounts[$type] = ($favoriteTypeCounts[$type] ?? 0) + 1;
            }
        }

        return [
            'reservedTypeCounts' => $reservedTypeCounts,
            'favoriteTypeCounts' => $favoriteTypeCounts,
            'reservedEventIds' => array_values($reservedEventIds),
            'isVipUser' => $this->loyaltyService->isVipEmail($email),
        ];
    }

    private function sanitizeEmail(string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}
