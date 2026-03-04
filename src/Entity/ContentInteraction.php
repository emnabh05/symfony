<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'content_interaction')]
class ContentInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column(length: 20)]
    private string $interactionType;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentText = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getTargetType(): string { return $this->targetType; }
    public function setTargetType(string $targetType): self { $this->targetType = $targetType; return $this; }

    public function getTargetId(): int { return $this->targetId; }
    public function setTargetId(int $targetId): self { $this->targetId = $targetId; return $this; }

    public function getInteractionType(): string { return $this->interactionType; }
    public function setInteractionType(string $interactionType): self { $this->interactionType = $interactionType; return $this; }

    public function getCommentText(): ?string { return $this->commentText; }
    public function setCommentText(?string $commentText): self { $this->commentText = $commentText; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
