<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'private_message')]
class DmMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DmConversation::class)]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?DmConversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'receiver_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $receiver = null;

    #[ORM\Column(name: 'content', type: 'text')]
    private string $body;

    #[ORM\Column(name: 'is_read', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private string $status = 'sent';

    private ?string $attachmentPath = null;

    private ?string $attachmentMime = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?DmConversation { return $this->conversation; }
    public function setConversation(?DmConversation $conversation): self { $this->conversation = $conversation; return $this; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): self { $this->sender = $sender; return $this; }

    public function getReceiver(): ?User { return $this->receiver; }
    public function setReceiver(?User $receiver): self { $this->receiver = $receiver; return $this; }

    public function getBody(): string { return $this->body; }
    public function setBody(string $body): self { $this->body = $body; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getStatus(): string
    {
        return $this->isRead ? 'read' : $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->isRead = $status === 'read';
        return $this;
    }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): self { $this->isRead = $isRead; return $this; }

    public function getAttachmentPath(): ?string { return $this->attachmentPath; }
    public function setAttachmentPath(?string $attachmentPath): self { $this->attachmentPath = $attachmentPath; return $this; }

    public function getAttachmentMime(): ?string { return $this->attachmentMime; }
    public function setAttachmentMime(?string $attachmentMime): self { $this->attachmentMime = $attachmentMime; return $this; }
}
