<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_event')]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(name: 'date_event', type: 'date_immutable')]
    private \DateTimeImmutable $dateEvent;

    #[ORM\Column(length: 150)]
    private string $lieu;

    #[ORM\Column]
    private int $capacite;

    #[ORM\Column(name: 'type_event', length: 100)]
    private string $typeEvent;

    #[ORM\Column(name: 'image_event', length: 255, nullable: true)]
    private ?string $imageEvent = null;

    #[ORM\Column(name: 'prix_event', type: 'decimal', precision: 10, scale: 2)]
    private string $prixEvent = '0.00';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'is_premium', type: 'boolean', options: ['default' => false])]
    private bool $isPremium = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDateEvent(): \DateTimeImmutable
{
    return $this->dateEvent;
}

public function setDateEvent(\DateTimeImmutable $dateEvent): self
{
    $this->dateEvent = $dateEvent;
    return $this;
}


    public function getLieu(): string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getTypeEvent(): string
    {
        return $this->typeEvent;
    }

    public function setTypeEvent(string $typeEvent): self
    {
        $this->typeEvent = $typeEvent;
        return $this;
    }

    public function getImageEvent(): ?string
    {
        return $this->imageEvent;
    }

    public function setImageEvent(?string $imageEvent): self
    {
        $this->imageEvent = $imageEvent;
        return $this;
    }

    public function getPrixEvent(): string
    {
        return $this->prixEvent;
    }

    public function setPrixEvent(?string $prixEvent): self
    {
        $this->prixEvent = $prixEvent ?? '0.00';
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

    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    public function setIsPremium(bool $isPremium): self
    {
        $this->isPremium = $isPremium;
        return $this;
    }
}
