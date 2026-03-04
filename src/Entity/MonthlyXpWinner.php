<?php

namespace App\Entity;

use App\Repository\MonthlyXpWinnerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyXpWinnerRepository::class)]
#[ORM\Table(name: 'monthly_xp_winner')]
#[ORM\UniqueConstraint(name: 'uniq_monthly_xp_winner_month', columns: ['month_key'])]
#[ORM\HasLifecycleCallbacks]
class MonthlyXpWinner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 7)]
    private string $monthKey = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private int $xp = 0;

    #[ORM\Column]
    private int $totalIntakes = 0;

    #[ORM\Column]
    private int $activeDays = 0;

    #[ORM\Column]
    private int $longestStreak = 0;

    #[ORM\Column(length: 255)]
    private string $rewardProductName = 'Free Product of the Month';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonthKey(): string
    {
        return $this->monthKey;
    }

    public function setMonthKey(string $monthKey): self
    {
        $this->monthKey = $monthKey;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function setXp(int $xp): self
    {
        $this->xp = max(0, $xp);
        return $this;
    }

    public function getTotalIntakes(): int
    {
        return $this->totalIntakes;
    }

    public function setTotalIntakes(int $totalIntakes): self
    {
        $this->totalIntakes = max(0, $totalIntakes);
        return $this;
    }

    public function getActiveDays(): int
    {
        return $this->activeDays;
    }

    public function setActiveDays(int $activeDays): self
    {
        $this->activeDays = max(0, $activeDays);
        return $this;
    }

    public function getLongestStreak(): int
    {
        return $this->longestStreak;
    }

    public function setLongestStreak(int $longestStreak): self
    {
        $this->longestStreak = max(0, $longestStreak);
        return $this;
    }

    public function getRewardProductName(): string
    {
        return $this->rewardProductName;
    }

    public function setRewardProductName(string $rewardProductName): self
    {
        $this->rewardProductName = trim($rewardProductName) !== ''
            ? trim($rewardProductName)
            : 'Free Product of the Month';
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
