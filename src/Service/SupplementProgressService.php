<?php

namespace App\Service;

use App\Entity\Supplement;
use App\Entity\SupplementIntakeLog;
use App\Entity\User;
use App\Repository\OrderItemRepository;
use App\Repository\SupplementIntakeLogRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class SupplementProgressService
{
    private const DEFAULT_BASE_DURATION_DAYS = 56;

    public function __construct(
        private readonly OrderItemRepository $orderItemRepository,
        private readonly SupplementIntakeLogRepository $supplementIntakeLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{summary: array<string, int|float>, cards: array<int, array<string, mixed>>}
     */
    public function buildDashboard(User $user): array
    {
        $purchases = $this->orderItemRepository->findPurchasedSupplementsForUser($user);
        $intakeDateMap = $this->supplementIntakeLogRepository->getDateMapByUser($user);

        $cards = [];
        $today = new \DateTimeImmutable('today');
        $totalCompliance = 0.0;
        $atRiskCount = 0;
        $totalXp = 0;
        $maxCurrentStreak = 0;

        foreach ($purchases as $purchase) {
            $supplement = $purchase['supplement'] ?? null;
            if (!$supplement instanceof Supplement || $supplement->getId() === null) {
                continue;
            }

            $firstPurchasedAt = $this->normalizeDate($purchase['firstPurchasedAt'] ?? null);
            if ($firstPurchasedAt === null) {
                continue;
            }

            $supplementId = (int) $supplement->getId();
            $dateKeys = $intakeDateMap[$supplementId] ?? [];
            $metrics = $this->computeMetrics(
                $firstPurchasedAt,
                $dateKeys,
                $supplement->getRecommendedDurationDays() ?: self::DEFAULT_BASE_DURATION_DAYS,
                $today
            );

            $cards[] = [
                'supplement' => $supplement,
                'firstPurchasedAt' => $firstPurchasedAt,
                'totalPurchasedQuantity' => (int) ($purchase['totalQuantity'] ?? 0),
                'takenDays' => $metrics['takenDays'],
                'totalDays' => $metrics['totalDays'],
                'compliance' => $metrics['compliance'],
                'currentStreak' => $metrics['currentStreak'],
                'bestStreak' => $metrics['bestStreak'],
                'daysSinceLastIntake' => $metrics['daysSinceLastIntake'],
                'estimatedDurationDays' => $metrics['estimatedDurationDays'],
                'estimatedGoalDate' => $metrics['estimatedGoalDate'],
                'estimatedProgress' => $metrics['estimatedProgress'],
                'badge' => $metrics['badge'],
                'riskDetected' => $metrics['riskDetected'],
                'xp' => $metrics['xp'],
                'feedback' => $metrics['feedback'],
                'todayTaken' => $metrics['todayTaken'],
            ];

            $totalCompliance += (float) $metrics['compliance'];
            $totalXp += (int) $metrics['xp'];
            $maxCurrentStreak = max($maxCurrentStreak, (int) $metrics['currentStreak']);
            if ($metrics['riskDetected']) {
                $atRiskCount++;
            }
        }

        $count = count($cards);

        return [
            'summary' => [
                'trackedSupplements' => $count,
                'averageCompliance' => $count > 0 ? round($totalCompliance / $count, 1) : 0.0,
                'atRiskCount' => $atRiskCount,
                'totalXp' => $totalXp,
                'maxCurrentStreak' => $maxCurrentStreak,
            ],
            'cards' => $cards,
        ];
    }

    /**
     * @return array{success: bool, reason: string}
     */
    public function markTakenToday(User $user, Supplement $supplement): array
    {
        $supplementId = $supplement->getId();
        if ($supplementId === null) {
            return ['success' => false, 'reason' => 'invalid_supplement'];
        }

        if (!$this->orderItemRepository->hasPurchasedSupplementForUser($user, $supplementId)) {
            return ['success' => false, 'reason' => 'not_purchased'];
        }

        $today = new \DateTimeImmutable('today');
        if ($this->supplementIntakeLogRepository->existsForDate($user, $supplement, $today)) {
            return ['success' => false, 'reason' => 'already_marked'];
        }

        $log = (new SupplementIntakeLog())
            ->setUserEmail((string) $user->getEmail())
            ->setSupplementId((int) $supplementId)
            ->setPlanId($this->supplementIntakeLogRepository->findOrCreatePlanId($user, $supplement))
            ->setIntakeDate($today)
            ->setTakenUnits(1);

        $this->entityManager->persist($log);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return ['success' => false, 'reason' => 'already_marked'];
        }

        return ['success' => true, 'reason' => 'created'];
    }

    /**
     * @param array<string, bool> $dateKeys
     * @return array{
     *     takenDays: int,
     *     totalDays: int,
     *     compliance: float,
     *     currentStreak: int,
     *     bestStreak: int,
     *     daysSinceLastIntake: int,
     *     estimatedDurationDays: int|null,
     *     estimatedGoalDate: \DateTimeImmutable|null,
     *     estimatedProgress: float,
     *     badge: string,
     *     riskDetected: bool,
     *     xp: int,
     *     feedback: string,
     *     todayTaken: bool
     * }
     */
    private function computeMetrics(
        \DateTimeImmutable $firstPurchasedAt,
        array $dateKeys,
        int $baseDurationDays,
        \DateTimeImmutable $today,
    ): array {
        $baseDurationDays = max(1, $baseDurationDays);
        $purchaseDay = $firstPurchasedAt->setTime(0, 0);

        $totalDays = max(1, ((int) $purchaseDay->diff($today)->days) + 1);
        $takenDays = count($dateKeys);
        $compliance = round(min(100, ($takenDays / $totalDays) * 100), 1);

        $todayKey = $today->format('Y-m-d');
        $todayTaken = isset($dateKeys[$todayKey]);

        $currentStreak = $this->calculateCurrentStreak($dateKeys, $today);
        $bestStreak = $this->calculateBestStreak($dateKeys);
        $lastIntakeDate = $this->getLastIntakeDate($dateKeys);
        $daysSinceLastIntake = $lastIntakeDate ? (int) $lastIntakeDate->diff($today)->days : $totalDays;

        $estimatedDurationDays = null;
        $estimatedGoalDate = null;
        if ($compliance > 0) {
            $estimatedDurationDays = (int) ceil($baseDurationDays / ($compliance / 100));
            $estimatedGoalDate = $purchaseDay->modify(sprintf('+%d days', max(0, $estimatedDurationDays - 1)));
        }

        $estimatedProgress = round(min(100, ($takenDays / $baseDurationDays) * 100), 1);
        $riskDetected = $compliance < 60 || $daysSinceLastIntake >= 3;
        $xp = ($takenDays * 10) + (intdiv($bestStreak, 7) * 100);

        return [
            'takenDays' => $takenDays,
            'totalDays' => $totalDays,
            'compliance' => $compliance,
            'currentStreak' => $currentStreak,
            'bestStreak' => $bestStreak,
            'daysSinceLastIntake' => $daysSinceLastIntake,
            'estimatedDurationDays' => $estimatedDurationDays,
            'estimatedGoalDate' => $estimatedGoalDate,
            'estimatedProgress' => $estimatedProgress,
            'badge' => $this->resolveBadge($compliance),
            'riskDetected' => $riskDetected,
            'xp' => $xp,
            'feedback' => $this->buildFeedback($compliance, $currentStreak, $daysSinceLastIntake, $riskDetected),
            'todayTaken' => $todayTaken,
        ];
    }

    /**
     * @param array<string, bool> $dateKeys
     */
    private function calculateCurrentStreak(array $dateKeys, \DateTimeImmutable $today): int
    {
        $streak = 0;
        $cursor = $today;

        while (isset($dateKeys[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /**
     * @param array<string, bool> $dateKeys
     */
    private function calculateBestStreak(array $dateKeys): int
    {
        if ($dateKeys === []) {
            return 0;
        }

        $dates = array_keys($dateKeys);
        sort($dates);

        $best = 1;
        $current = 1;
        $previous = new \DateTimeImmutable($dates[0]);

        for ($i = 1; $i < count($dates); $i++) {
            $currentDate = new \DateTimeImmutable($dates[$i]);
            $gap = (int) $previous->diff($currentDate)->days;

            if ($gap === 1) {
                $current++;
                $best = max($best, $current);
            } else {
                $current = 1;
            }

            $previous = $currentDate;
        }

        return $best;
    }

    /**
     * @param array<string, bool> $dateKeys
     */
    private function getLastIntakeDate(array $dateKeys): ?\DateTimeImmutable
    {
        if ($dateKeys === []) {
            return null;
        }

        $dates = array_keys($dateKeys);
        rsort($dates);

        return new \DateTimeImmutable($dates[0]);
    }

    private function resolveBadge(float $compliance): string
    {
        if ($compliance >= 90) {
            return 'Elite';
        }

        if ($compliance >= 75) {
            return 'Consistent';
        }

        if ($compliance >= 50) {
            return 'Average';
        }

        return 'At Risk';
    }

    private function buildFeedback(float $compliance, int $currentStreak, int $daysSinceLastIntake, bool $risk): string
    {
        if ($risk && $daysSinceLastIntake >= 3) {
            return 'Risk of abandonment detected. Activate reminders and simplify your stack.';
        }

        if ($risk) {
            return 'Your discipline is dropping. Turn on reminders and lock a fixed intake time.';
        }

        if ($compliance >= 90 && $currentStreak >= 5) {
            return 'Excellent discipline. Keep this rhythm.';
        }

        if ($compliance >= 75) {
            return 'Strong consistency. You are on track.';
        }

        return 'You can improve regularity. Try taking it at the same hour daily.';
    }

    private function normalizeDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTime(0, 0);
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTime(0, 0);
        }

        if (is_string($value) && trim($value) !== '') {
            return (new \DateTimeImmutable($value))->setTime(0, 0);
        }

        return null;
    }
}
