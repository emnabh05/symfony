<?php

namespace App\Entity;

use App\Repository\SupplementIntakeLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplementIntakeLogRepository::class)]
#[ORM\Table(name: 'supplement_adherence_logs')]
#[ORM\HasLifecycleCallbacks]
class SupplementIntakeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'plan_id')]
    private int $planId = 0;

    #[ORM\Column(name: 'user_email', length: 255)]
    private string $userEmail = '';

    #[ORM\Column(name: 'supplement_id')]
    private int $supplementId = 0;

    #[ORM\Column(name: 'log_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $intakeDate;

    #[ORM\Column(name: 'taken_units')]
    private int $takenUnits = 1;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->intakeDate = (new \DateTimeImmutable('today'))->setTime(0, 0);
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

    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getSupplementId(): int
    {
        return $this->supplementId;
    }

    public function setSupplementId(int $supplementId): self
    {
        $this->supplementId = $supplementId;
        return $this;
    }

    public function getPlanId(): int
    {
        return $this->planId;
    }

    public function setPlanId(int $planId): self
    {
        $this->planId = $planId;
        return $this;
    }

    public function getIntakeDate(): \DateTimeImmutable
    {
        return $this->intakeDate;
    }

    public function setIntakeDate(\DateTimeImmutable $intakeDate): self
    {
        $this->intakeDate = $intakeDate->setTime(0, 0);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTakenUnits(): int
    {
        return $this->takenUnits;
    }

    public function setTakenUnits(int $takenUnits): self
    {
        $this->takenUnits = max(1, $takenUnits);
        return $this;
    }
}
