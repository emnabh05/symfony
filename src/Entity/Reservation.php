<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    public const STATUS_PENDING_PAYMENT = 'EN_ATTENTE_PAIEMENT';
    public const STATUS_PAID = 'Payee';
    public const STATUS_CONFIRMED = 'Confirmee';
    public const STATUS_USED = 'Utilisee';
    public const STATUS_CANCELLED = 'Annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'id_event', referencedColumnName: 'id_event', nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(name: 'nom_participant', length: 150)]
    private string $nomParticipant = '';

    #[ORM\Column(name: 'email_participant', length: 150)]
    private string $emailParticipant = '';

    #[ORM\Column(name: 'telephone_participant', length: 30, nullable: true)]
    private ?string $telephoneParticipant = null;

    #[ORM\Column(name: 'date_reservation', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateReservation;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $montant = null;

    #[ORM\Column(length: 30)]
    private string $statut = self::STATUS_CONFIRMED;

    #[ORM\Column(name: 'transaction_id', length: 255, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(name: 'qr_token', length: 64, unique: true, nullable: true)]
    #[Ignore]
    private ?string $qrToken = null;

    #[ORM\Column(name: 'checked_in_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'qr_generated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $qrGeneratedAt = null;

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

    public function getEvenement(): ?Event
    {
        return $this->getEvent();
    }

    public function setEvenement(?Event $evenement): self
    {
        return $this->setEvent($evenement);
    }

    public function getNomParticipant(): string
    {
        return $this->nomParticipant;
    }

    public function setNomParticipant(string $nomParticipant): self
    {
        $this->nomParticipant = $nomParticipant;
        return $this;
    }

    public function getEmailParticipant(): string
    {
        return $this->emailParticipant;
    }

    public function setEmailParticipant(string $emailParticipant): self
    {
        $this->emailParticipant = $emailParticipant;
        return $this;
    }

    public function getTelephoneParticipant(): ?string
    {
        return $this->telephoneParticipant;
    }

    public function setTelephoneParticipant(?string $telephoneParticipant): self
    {
        $this->telephoneParticipant = $telephoneParticipant;
        return $this;
    }

    public function getDateReservation(): \DateTimeImmutable
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeImmutable $dateReservation): self
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(?string $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getQrToken(): ?string
    {
        return $this->qrToken;
    }

    public function setQrToken(?string $qrToken): self
    {
        $this->qrToken = $qrToken;
        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(?\DateTimeImmutable $checkedInAt): self
    {
        $this->checkedInAt = $checkedInAt;
        return $this;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeImmutable $usedAt): self
    {
        $this->usedAt = $usedAt;
        return $this;
    }

    public function getQrGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->qrGeneratedAt;
    }

    public function setQrGeneratedAt(?\DateTimeImmutable $qrGeneratedAt): self
    {
        $this->qrGeneratedAt = $qrGeneratedAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->statut === self::STATUS_USED || $this->usedAt !== null;
    }
}
