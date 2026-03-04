<?php

namespace App\Service;

use App\Entity\RegimeAlimentaire;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class DailyGoalMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function sendGoalReachedSheet(
        User $user,
        RegimeAlimentaire $regime,
        int $beforeCalories,
        int $afterCalories
    ): void {
        $to = trim($user->getEmail());
        if ($to === '') {
            throw new \RuntimeException('User email is missing.');
        }

        $target = (int) ($regime->getCaloriesCibles() ?? 0);
        if ($target <= 0) {
            throw new \RuntimeException('Calorie target is not configured for this regime.');
        }

        $progress = (int) round(($afterCalories / $target) * 100);
        $excess = max(0, $afterCalories - $target);
        $remaining = max(0, $target - $afterCalories);

        $from = trim((string) ($_ENV['MAILER_FROM'] ?? $_SERVER['MAILER_FROM'] ?? 'no-reply@fitopia.local'));

        $email = (new TemplatedEmail())
            ->from(new Address($from, 'Fitopia Nutrition'))
            ->to(new Address($to))
            ->subject('Objectif calorique atteint - fiche nutrition')
            ->htmlTemplate('emails/daily_goal_reached.html.twig')
            ->context([
                'today' => new \DateTimeImmutable(),
                'user' => $user,
                'regime' => $regime,
                'target' => $target,
                'beforeCalories' => $beforeCalories,
                'afterCalories' => $afterCalories,
                'progress' => $progress,
                'excess' => $excess,
                'remaining' => $remaining,
            ]);

        $this->mailer->send($email);
    }
}

