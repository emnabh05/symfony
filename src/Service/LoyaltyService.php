<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ReservationRepository;

class LoyaltyService
{
    public const VIP_RESERVATION_THRESHOLD = 5;
    public const TIER_NONE = 'None';
    public const TIER_BRONZE = 'Bronze';
    public const TIER_SILVER = 'Silver';
    public const TIER_GOLD = 'Gold';
    public const TIER_VIP = 'VIP';

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    public function computeTier(int $count, float $total): string
    {
        if ($this->isVipCount($count)) {
            return self::TIER_VIP;
        }
        if ($count >= 4) {
            return self::TIER_GOLD;
        }
        if ($count >= 2) {
            return self::TIER_SILVER;
        }
        if ($count >= 1) {
            return self::TIER_BRONZE;
        }

        return self::TIER_NONE;
    }

    /**
     * @return array<int, array{title: string, text: string}>
     */
    public function benefitsForTier(string $tier): array
    {
        return match ($tier) {
            self::TIER_VIP => [
                ['title' => 'VIP access', 'text' => 'Access VIP events and priority handling for reservations.'],
                ['title' => 'Fast check-in', 'text' => 'Dedicated queue and smoother event entry.'],
            ],
            self::TIER_GOLD => [
                ['title' => 'Priority reservations', 'text' => 'Highlighted booking experience on event pages.'],
                ['title' => 'Premium support', 'text' => 'Faster assistance for event-related requests.'],
            ],
            self::TIER_SILVER => [
                ['title' => 'Priority reservations', 'text' => 'You get a clearer and faster booking flow.'],
                ['title' => 'Member recognition', 'text' => 'Your loyalty status is visible in your account space.'],
            ],
            self::TIER_BRONZE => [
                ['title' => 'Welcome tier', 'text' => 'You are now in the loyalty program.'],
                ['title' => 'Progress tracking', 'text' => 'Track your progress toward Silver advantages.'],
            ],
            default => [
                ['title' => 'Get started', 'text' => 'Confirm your first reservation to unlock Bronze status.'],
            ],
        };
    }

    /**
     * @return array{current: int, needed: int, nextTier: string}
     */
    public function nextTierProgress(int $count): array
    {
        if ($count <= 0) {
            return ['current' => 0, 'needed' => 1, 'nextTier' => self::TIER_BRONZE];
        }
        if ($count < 2) {
            return ['current' => $count, 'needed' => 2, 'nextTier' => self::TIER_SILVER];
        }
        if ($count < 4) {
            return ['current' => $count, 'needed' => 4, 'nextTier' => self::TIER_GOLD];
        }
        if ($count < self::VIP_RESERVATION_THRESHOLD) {
            return ['current' => $count, 'needed' => self::VIP_RESERVATION_THRESHOLD, 'nextTier' => self::TIER_VIP];
        }

        return ['current' => $count, 'needed' => $count, 'nextTier' => self::TIER_VIP];
    }

    public function computeScore(int $count, float $total): int
    {
        return ($count * 10) + (int) floor($total);
    }

    public function getVipReservationThreshold(): int
    {
        return self::VIP_RESERVATION_THRESHOLD;
    }

    public function isVipCount(int $count): bool
    {
        return $count >= self::VIP_RESERVATION_THRESHOLD;
    }

    public function isVipEmail(string $email): bool
    {
        return $this->isVipCount($this->getReservationCount($email));
    }

    public function getReservationCount(string $email): int
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }

        $stats = $this->reservationRepository->getLoyaltyStatsForEmail($email);

        return (int) ($stats['countConfirmed'] ?? 0);
    }

    public function isVip(User $user): bool
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_VIP', $roles, true)) {
            return true;
        }

        return $this->isVipEmail((string) $user->getEmail());
    }

    /**
     * @return array{nextTier: string, months: int, targetDate: \DateTimeImmutable, message: string}|null
     */
    public function predictNextTierDate(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $stats = $this->reservationRepository->getLoyaltyStatsForEmail($email);
        $count = (int) ($stats['countConfirmed'] ?? 0);
        $total = (float) ($stats['totalAmount'] ?? 0.0);
        $currentTier = $this->computeTier($count, $total);
        if ($currentTier === self::TIER_VIP) {
            return null;
        }

        $progress = $this->nextTierProgress($count);
        $needed = max(0, (int) (($progress['needed'] ?? 0) - ($progress['current'] ?? 0)));
        if ($needed <= 0) {
            return null;
        }

        $pace = $this->reservationRepository->getReservationPaceForEmail($email);
        $perMonth = (float) ($pace['perMonth'] ?? 0.0);
        if ($perMonth <= 0) {
            return null;
        }

        $months = max(1, (int) ceil($needed / $perMonth));
        $targetDate = (new \DateTimeImmutable('today'))->modify('+'.$months.' months');
        $nextTier = (string) ($progress['nextTier'] ?? self::TIER_BRONZE);

        return [
            'nextTier' => $nextTier,
            'months' => $months,
            'targetDate' => $targetDate,
            'message' => sprintf(
                'A votre rythme actuel, vous pourriez devenir %s dans %d mois.',
                $nextTier,
                $months
            ),
        ];
    }
}
