<?php

namespace App\Entity;

use App\Repository\FavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriteRepository::class)]
#[ORM\Table(
    name: 'favorites',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_FAVORITES_EVENT_EMAIL', columns: ['id_event', 'email_participant'])],
    indexes: [new ORM\Index(name: 'IDX_FAVORITES_EMAIL', columns: ['email_participant'])]
)]
class Favorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'id_event', referencedColumnName: 'id_event', nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(name: 'email_participant', length: 180)]
    private string $emailParticipant = '';

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

    public function getEmailParticipant(): string
    {
        return $this->emailParticipant;
    }

    public function setEmailParticipant(string $emailParticipant): self
    {
        $this->emailParticipant = mb_strtolower(trim($emailParticipant));
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
