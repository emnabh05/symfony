<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dm_participant')]
class DmParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DmConversation::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DmConversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastReadAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $typingAt = null;

    public function getId(): ?int { return $this->id; }

    public function getConversation(): ?DmConversation { return $this->conversation; }
    public function setConversation(?DmConversation $conversation): self { $this->conversation = $conversation; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getLastReadAt(): ?\DateTimeImmutable { return $this->lastReadAt; }
    public function setLastReadAt(?\DateTimeImmutable $lastReadAt): self { $this->lastReadAt = $lastReadAt; return $this; }

    public function getTypingAt(): ?\DateTimeImmutable { return $this->typingAt; }
    public function setTypingAt(?\DateTimeImmutable $typingAt): self { $this->typingAt = $typingAt; return $this; }
}
