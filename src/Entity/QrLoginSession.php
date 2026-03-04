<?php

namespace App\Entity;

use App\Repository\QrLoginSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: QrLoginSessionRepository::class)]
#[ORM\Table(name: 'qr_login_sessions')]
#[ORM\Index(columns: ['token_hash'], name: 'idx_qr_token_hash')]
#[ORM\Index(columns: ['status', 'expires_at'], name: 'idx_qr_status_expires')]
#[ORM\Index(columns: ['created_at'], name: 'idx_qr_created')]
class QrLoginSession
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONSUMED = 'consumed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Ignore]
    private string $tokenHash;

    #[ORM\Column(length: 64)]
    private string $initiatorSessionHash;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $initiatorUserAgentHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $initiatorIpHash = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedByUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(#[\SensitiveParameter] string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getInitiatorSessionHash(): string
    {
        return $this->initiatorSessionHash;
    }

    public function setInitiatorSessionHash(string $initiatorSessionHash): self
    {
        $this->initiatorSessionHash = $initiatorSessionHash;
        return $this;
    }

    public function getInitiatorUserAgentHash(): ?string
    {
        return $this->initiatorUserAgentHash;
    }

    public function setInitiatorUserAgentHash(?string $initiatorUserAgentHash): self
    {
        $this->initiatorUserAgentHash = $initiatorUserAgentHash;
        return $this;
    }

    public function getInitiatorIpHash(): ?string
    {
        return $this->initiatorIpHash;
    }

    public function setInitiatorIpHash(?string $initiatorIpHash): self
    {
        $this->initiatorIpHash = $initiatorIpHash;
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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
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

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function setConsumedAt(?\DateTimeImmutable $consumedAt): self
    {
        $this->consumedAt = $consumedAt;
        return $this;
    }

    public function getApprovedByUser(): ?User
    {
        return $this->approvedByUser;
    }

    public function setApprovedByUser(?User $approvedByUser): self
    {
        $this->approvedByUser = $approvedByUser;
        return $this;
    }
}
