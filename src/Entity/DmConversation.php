<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'private_conversation')]
class DmConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_a_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $userA = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_b_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $userB = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastMessageAt;

    public function __construct()
    {
        $this->lastMessageAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->lastMessageAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->lastMessageAt = $createdAt; return $this; }

    public function getLastMessageAt(): ?\DateTimeImmutable { return $this->lastMessageAt; }
    public function setLastMessageAt(?\DateTimeImmutable $lastMessageAt): self
    {
        $this->lastMessageAt = $lastMessageAt ?? new \DateTimeImmutable();
        return $this;
    }

    public function getUserA(): ?User { return $this->userA; }
    public function setUserA(?User $userA): self { $this->userA = $userA; return $this; }

    public function getUserB(): ?User { return $this->userB; }
    public function setUserB(?User $userB): self { $this->userB = $userB; return $this; }
}
