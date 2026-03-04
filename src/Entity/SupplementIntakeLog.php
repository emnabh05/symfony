<?php

namespace App\Entity;

use App\Repository\SupplementIntakeLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplementIntakeLogRepository::class)]
#[ORM\Table(
    name: 'supplement_intake_log',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_intake_user_supplement_date',
            columns: ['user_id', 'supplement_id', 'intake_date']
        ),
    ],
    indexes: [
        new ORM\Index(name: 'idx_intake_user_date', columns: ['user_id', 'intake_date']),
    ]
)]
#[ORM\HasLifecycleCallbacks]
class SupplementIntakeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Supplement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Supplement $supplement = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $intakeDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getSupplement(): ?Supplement
    {
        return $this->supplement;
    }

    public function setSupplement(Supplement $supplement): self
    {
        $this->supplement = $supplement;
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
}
