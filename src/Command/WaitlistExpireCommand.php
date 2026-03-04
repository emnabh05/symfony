<?php

namespace App\Command;

use App\Service\WaitlistService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:waitlist:expire',
    description: 'Expire les invitations waitlist depassees et invite le prochain participant',
)]
class WaitlistExpireCommand extends Command
{
    public function __construct(
        private readonly WaitlistService $waitlistService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->waitlistService->expireInvites();
        $io->success(sprintf('%d invitation(s) expiree(s).', $count));

        return Command::SUCCESS;
    }
}
