<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'supplement_order_notifications')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'recipient_email', length: 255)]
    private string $email = '';

    private ?string $orderNumberOverride = null;

    #[ORM\Column(name: 'previous_status', length: 50, nullable: true)]
    private ?string $previousStatus = null;

    #[ORM\Column(name: 'new_status', length: 50)]
    private string $status = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    private ?\DateTimeInterface $readAt = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $orderRef = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getOrderNumber(): string
    {
        if ($this->orderRef instanceof Order) {
            return $this->orderRef->getOrderNumber();
        }

        return $this->orderNumberOverride ?? '';
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $normalized = trim($orderNumber);
        $this->orderNumberOverride = $normalized !== '' ? $normalized : null;
        return $this;
    }

    public function getStatus(): string
    {
        return Order::normalizeStatusForDisplay($this->status);
    }

    public function setStatus(string $status): static
    {
        $this->status = Order::normalizeStatusForStorage($status);
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus !== null
            ? Order::normalizeStatusForDisplay($this->previousStatus)
            : null;
    }

    public function setPreviousStatus(?string $previousStatus): static
    {
        $this->previousStatus = $previousStatus !== null
            ? Order::normalizeStatusForStorage($previousStatus)
            : null;

        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getOrderRef(): ?Order
    {
        return $this->orderRef;
    }

    public function setOrderRef(?Order $orderRef): static
    {
        $this->orderRef = $orderRef;
        return $this;
    }
}
