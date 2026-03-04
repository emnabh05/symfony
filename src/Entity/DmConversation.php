<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'dm_conversation')]
class DmConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMessageAt = null;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: DmParticipant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participants;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: DmMessage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getLastMessageAt(): ?\DateTimeImmutable { return $this->lastMessageAt; }
    public function setLastMessageAt(?\DateTimeImmutable $lastMessageAt): self { $this->lastMessageAt = $lastMessageAt; return $this; }

    /** @return Collection<int, DmParticipant> */
    public function getParticipants(): Collection { return $this->participants; }

    /** @return Collection<int, DmMessage> */
    public function getMessages(): Collection { return $this->messages; }
}
