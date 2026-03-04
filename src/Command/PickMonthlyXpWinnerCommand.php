<?php

namespace App\Command;

use App\Service\MonthlyLeaderboardService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:leaderboard:pick-monthly-winner',
    description: 'Select and store the monthly XP leaderboard winner.'
)]
class PickMonthlyXpWinnerCommand extends Command
{
    public function __construct(private readonly MonthlyLeaderboardService $monthlyLeaderboardService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('month', null, InputOption::VALUE_REQUIRED, 'Month in YYYY-MM format (default: previous month)')
            ->addOption('reward', null, InputOption::VALUE_REQUIRED, 'Reward product name', 'Free Product of the Month')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show result without saving winner');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $monthOption = trim((string) $input->getOption('month'));
        $reward = trim((string) $input->getOption('reward'));
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $month = $monthOption !== ''
                ? $this->monthlyLeaderboardService->resolveMonth($monthOption)
                : (new \DateTimeImmutable('first day of last month'))->setTime(0, 0);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $result = $this->monthlyLeaderboardService->pickWinnerForMonth(
            $month,
            $reward !== '' ? $reward : 'Free Product of the Month',
            $dryRun
        );

        $status = $result['status'] ?? 'unknown';
        $winner = $result['winner'] ?? null;

        if ($status === 'no_data') {
            $io->warning(sprintf('No intake data found for %s.', $month->format('Y-m')));
            return Command::SUCCESS;
        }

        if (!is_array($winner)) {
            $io->error('Winner data is missing.');
            return Command::FAILURE;
        }

        $io->definitionList(
            ['Month' => $winner['monthKey'] ?? $month->format('Y-m')],
            ['Winner' => (string) ($winner['displayName'] ?? 'Unknown')],
            ['XP' => (string) ($winner['xp'] ?? 0)],
            ['Total Intakes' => (string) ($winner['totalIntakes'] ?? 0)],
            ['Active Days' => (string) ($winner['activeDays'] ?? 0)],
            ['Longest Streak' => (string) ($winner['longestStreak'] ?? 0)],
            ['Reward' => (string) ($winner['rewardProductName'] ?? $reward)],
        );

        if ($status === 'already_selected') {
            $io->note('A winner is already stored for this month.');
            return Command::SUCCESS;
        }

        if ($status === 'dry_run') {
            $io->success('Dry run complete. No winner was persisted.');
            return Command::SUCCESS;
        }

        if ($status === 'created') {
            $io->success('Monthly winner stored successfully.');
            return Command::SUCCESS;
        }

        $io->warning('Unexpected status: ' . $status);
        return Command::SUCCESS;
    }
}

