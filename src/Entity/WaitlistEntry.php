<?php

namespace App\Entity;

use App\Repository\WaitlistEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: WaitlistEntryRepository::class)]
#[ORM\Table(name: 'waitlist_entry')]
#[ORM\UniqueConstraint(name: 'UNIQ_WAITLIST_EVENT_EMAIL', columns: ['id_event', 'email'])]
#[ORM\Index(name: 'IDX_WAITLIST_EVENT_STATUS_CREATED', columns: ['id_event', 'status', 'created_at'])]
class WaitlistEntry
{
    // Legacy aliases for compatibility with other project versions.
    public const STATUS_WAITING = 'EN_ATTENTE';
    public const STATUS_PROMOTED = 'CONFIRMEE';
    public const STATUS_EXPIRED = 'EXPIREE';

    public const STATUS_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUS_INVITE = 'INVITE';
    public const STATUS_CONFIRMEE = 'CONFIRMEE';
    public const STATUS_EXPIREE = 'EXPIREE';
    public const STATUS_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'id_event', referencedColumnName: 'id_event', nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(length: 150)]
    private string $email = '';

    #[ORM\Column(length: 150)]
    private string $nom = '';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_EN_ATTENTE;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Ignore]
    private ?string $token = null;

    #[ORM\Column(name: 'invited_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getInvitedAt(): ?\DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?\DateTimeImmutable $invitedAt): self
    {
        $this->invitedAt = $invitedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
