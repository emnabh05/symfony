<?php

namespace App\Entity;

use App\Repository\EventReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventReviewRepository::class)]
#[ORM\Table(
    name: 'reviews',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_REVIEWS_EVENT_EMAIL', columns: ['id_event', 'email_participant'])],
    indexes: [new ORM\Index(name: 'IDX_REVIEWS_EVENT', columns: ['id_event'])]
)]
class EventReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'id_event', referencedColumnName: 'id_event', nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(name: 'email_participant', length: 180)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: 'Veuillez entrer un email valide.')]
    private string $emailParticipant = '';

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit etre comprise entre {{ min }} et {{ max }}.'
    )]
    private int $note = 5;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le commentaire est obligatoire.')]
    #[Assert\Length(min: 5, minMessage: 'Le commentaire doit contenir au moins 5 caracteres.')]
    private string $commentaire = '';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function getCommentaire(): string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): self
    {
        $this->commentaire = trim($commentaire);
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
