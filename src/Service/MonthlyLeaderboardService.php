<?php

namespace App\Service;

use App\Entity\MonthlyXpWinner;
use App\Entity\User;
use App\Repository\MonthlyXpWinnerRepository;
use App\Repository\SupplementIntakeLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class MonthlyLeaderboardService
{
    public function __construct(
        private readonly SupplementIntakeLogRepository $supplementIntakeLogRepository,
        private readonly MonthlyXpWinnerRepository $monthlyXpWinnerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolveMonth(?string $month): \DateTimeImmutable
    {
        $month = trim((string) $month);
        if ($month === '') {
            return (new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Invalid month format. Expected YYYY-MM.');
        }

        return (new \DateTimeImmutable($month . '-01'))->setTime(0, 0);
    }

    /**
     * @return array{
     *     monthKey: string,
     *     monthLabel: string,
     *     startDate: \DateTimeImmutable,
     *     endDate: \DateTimeImmutable,
     *     entries: array<int, array<string, mixed>>
     * }
     */
    public function buildMonthlyLeaderboard(\DateTimeImmutable $month, int $limit = 100): array
    {
        $startDate = $month->modify('first day of this month')->setTime(0, 0);
        $endDate = $month->modify('last day of this month')->setTime(0, 0);

        $rows = $this->supplementIntakeLogRepository->findLeaderboardRowsBetween($startDate, $endDate);
        $entries = $this->buildEntries($rows, $endDate);

        if ($limit > 0 && count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
        }

        return [
            'monthKey' => $startDate->format('Y-m'),
            'monthLabel' => $startDate->format('F Y'),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'entries' => $entries,
        ];
    }

    public function getWinnerForMonth(\DateTimeImmutable $month): ?MonthlyXpWinner
    {
        return $this->monthlyXpWinnerRepository->findOneByMonthKey($month->format('Y-m'));
    }

    /**
     * @return array<int, MonthlyXpWinner>
     */
    public function getLatestWinners(int $limit = 6): array
    {
        return $this->monthlyXpWinnerRepository->findLatest($limit);
    }

    /**
     * @return array{status: string, winner: array<string, mixed>|null}
     */
    public function pickWinnerForMonth(
        \DateTimeImmutable $month,
        string $rewardProductName = 'Free Product of the Month',
        bool $dryRun = false,
    ): array {
        $monthKey = $month->format('Y-m');
        $existing = $this->monthlyXpWinnerRepository->findOneByMonthKey($monthKey);

        if ($existing !== null) {
            return [
                'status' => 'already_selected',
                'winner' => $this->mapWinner($existing),
            ];
        }

        $board = $this->buildMonthlyLeaderboard($month, 1);
        $entries = $board['entries'];
        if ($entries === []) {
            return ['status' => 'no_data', 'winner' => null];
        }

        $winnerEntry = $entries[0];
        if ($dryRun) {
            return ['status' => 'dry_run', 'winner' => $winnerEntry];
        }

        $winnerUserId = (int) ($winnerEntry['userId'] ?? 0);
        /** @var User|null $winnerUser */
        $winnerUser = $winnerUserId > 0 ? $this->entityManager->find(User::class, $winnerUserId) : null;
        if (!$winnerUser instanceof User) {
            return ['status' => 'no_data', 'winner' => null];
        }

        $winner = (new MonthlyXpWinner())
            ->setMonthKey($monthKey)
            ->setUser($winnerUser)
            ->setXp((int) ($winnerEntry['xp'] ?? 0))
            ->setTotalIntakes((int) ($winnerEntry['totalIntakes'] ?? 0))
            ->setActiveDays((int) ($winnerEntry['activeDays'] ?? 0))
            ->setLongestStreak((int) ($winnerEntry['longestStreak'] ?? 0))
            ->setRewardProductName($rewardProductName);

        $this->entityManager->persist($winner);
        $this->entityManager->flush();

        return [
            'status' => 'created',
            'winner' => $this->mapWinner($winner),
        ];
    }

    /**
     * @param array<int, array{
     *     userId: mixed,
     *     username: mixed,
     *     firstName: mixed,
     *     lastName: mixed,
     *     email: mixed,
     *     intakeDate: mixed
     * }> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildEntries(array $rows, \DateTimeImmutable $monthEnd): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $userId = (int) ($row['userId'] ?? 0);
            $dateKey = $this->normalizeDateKey($row['intakeDate'] ?? null);

            if ($userId <= 0 || $dateKey === null) {
                continue;
            }

            if (!isset($groups[$userId])) {
                $groups[$userId] = [
                    'userId' => $userId,
                    'username' => (string) ($row['username'] ?? ''),
                    'firstName' => (string) ($row['firstName'] ?? ''),
                    'lastName' => (string) ($row['lastName'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'totalIntakes' => 0,
                    'dateMap' => [],
                ];
            }

            $groups[$userId]['totalIntakes']++;
            $groups[$userId]['dateMap'][$dateKey] = true;
        }

        $entries = [];
        foreach ($groups as $group) {
            $dateKeys = array_keys($group['dateMap']);
            sort($dateKeys);

            $totalIntakes = (int) $group['totalIntakes'];
            $activeDays = count($dateKeys);
            $longestStreak = $this->calculateLongestStreak($dateKeys);
            $currentStreak = $this->calculateCurrentStreak($dateKeys, $monthEnd);
            $weeklyBonus = $this->calculateWeeklyBonus($dateKeys);
            $xp = ($totalIntakes * 10) + $weeklyBonus;

            $displayName = trim($group['firstName'] . ' ' . $group['lastName']);
            if ($displayName === '') {
                $displayName = (string) $group['username'];
            }

            $entries[] = [
                'userId' => (int) $group['userId'],
                'username' => (string) $group['username'],
                'displayName' => $displayName !== '' ? $displayName : 'User #' . (int) $group['userId'],
                'email' => (string) $group['email'],
                'xp' => $xp,
                'totalIntakes' => $totalIntakes,
                'activeDays' => $activeDays,
                'longestStreak' => $longestStreak,
                'currentStreak' => $currentStreak,
                'weeklyBonus' => $weeklyBonus,
                'firstIntake' => $dateKeys[0] ?? '9999-12-31',
            ];
        }

        usort($entries, function (array $a, array $b): int {
            return
                ($b['xp'] <=> $a['xp'])
                ?: ($b['longestStreak'] <=> $a['longestStreak'])
                ?: ($b['activeDays'] <=> $a['activeDays'])
                ?: ($b['totalIntakes'] <=> $a['totalIntakes'])
                ?: (($a['firstIntake'] <=> $b['firstIntake']))
                ?: (($a['userId'] <=> $b['userId']));
        });

        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
            unset($entry['firstIntake']);
        }

        return $entries;
    }

    /**
     * @param array<int, string> $dateKeysSorted
     */
    private function calculateLongestStreak(array $dateKeysSorted): int
    {
        if ($dateKeysSorted === []) {
            return 0;
        }

        $longest = 1;
        $current = 1;
        $previous = new \DateTimeImmutable($dateKeysSorted[0]);

        for ($i = 1, $len = count($dateKeysSorted); $i < $len; $i++) {
            $date = new \DateTimeImmutable($dateKeysSorted[$i]);
            $gap = (int) $previous->diff($date)->days;

            if ($gap === 1) {
                $current++;
            } else {
                $current = 1;
            }

            $longest = max($longest, $current);
            $previous = $date;
        }

        return $longest;
    }

    /**
     * @param array<int, string> $dateKeysSorted
     */
    private function calculateCurrentStreak(array $dateKeysSorted, \DateTimeImmutable $monthEnd): int
    {
        if ($dateKeysSorted === []) {
            return 0;
        }

        $dateMap = array_fill_keys($dateKeysSorted, true);
        $streak = 0;
        $cursor = $monthEnd;

        while (isset($dateMap[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /**
     * @param array<int, string> $dateKeysSorted
     */
    private function calculateWeeklyBonus(array $dateKeysSorted): int
    {
        if ($dateKeysSorted === []) {
            return 0;
        }

        $bonus = 0;
        $streakLength = 1;
        $previous = new \DateTimeImmutable($dateKeysSorted[0]);

        for ($i = 1, $len = count($dateKeysSorted); $i < $len; $i++) {
            $date = new \DateTimeImmutable($dateKeysSorted[$i]);
            $gap = (int) $previous->diff($date)->days;

            if ($gap === 1) {
                $streakLength++;
            } else {
                $bonus += intdiv($streakLength, 7) * 100;
                $streakLength = 1;
            }

            $previous = $date;
        }

        $bonus += intdiv($streakLength, 7) * 100;

        return $bonus;
    }

    private function normalizeDateKey(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date) && trim($date) !== '') {
            return (new \DateTimeImmutable($date))->format('Y-m-d');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWinner(MonthlyXpWinner $winner): array
    {
        $user = $winner->getUser();
        $displayName = $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) : '';
        if ($displayName === '' && $user) {
            $displayName = $user->getUsername();
        }

        return [
            'monthKey' => $winner->getMonthKey(),
            'displayName' => $displayName !== '' ? $displayName : 'Unknown',
            'username' => $user?->getUsername(),
            'email' => $user?->getEmail(),
            'xp' => $winner->getXp(),
            'totalIntakes' => $winner->getTotalIntakes(),
            'activeDays' => $winner->getActiveDays(),
            'longestStreak' => $winner->getLongestStreak(),
            'rewardProductName' => $winner->getRewardProductName(),
            'createdAt' => $winner->getCreatedAt(),
        ];
    }
}

