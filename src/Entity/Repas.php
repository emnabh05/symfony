<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repas')]
class Repas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_repas')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateRepas;

    #[ORM\Column(length: 32)]
    private string $typeRepas;

    #[ORM\Column(length: 255)]
    private string $nomRepas;

    #[ORM\Column(nullable: true)]
    private ?int $calories = null;

    #[ORM\Column(nullable: true)]
    private ?int $proteines = null;

    #[ORM\Column(nullable: true)]
    private ?int $glucides = null;

    #[ORM\Column(nullable: true)]
    private ?int $lipides = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(targetEntity: RegimeAlimentaire::class, inversedBy: 'repas')]
    #[ORM\JoinColumn(name: 'regime_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RegimeAlimentaire $regime = null;

    public function __construct()
    {
        $this->dateRepas = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDateRepas(): \DateTimeImmutable { return $this->dateRepas; }
    public function setDateRepas(\DateTimeImmutable $dateRepas): self { $this->dateRepas = $dateRepas; return $this; }

    public function getTypeRepas(): string { return $this->typeRepas; }
    public function setTypeRepas(string $typeRepas): self { $this->typeRepas = $typeRepas; return $this; }

    public function getNomRepas(): string { return $this->nomRepas; }
    public function setNomRepas(string $nomRepas): self { $this->nomRepas = $nomRepas; return $this; }

    public function getCalories(): ?int { return $this->calories; }
    public function setCalories(?int $calories): self { $this->calories = $calories; return $this; }

    public function getProteines(): ?int { return $this->proteines; }
    public function setProteines(?int $proteines): self { $this->proteines = $proteines; return $this; }

    public function getGlucides(): ?int { return $this->glucides; }
    public function setGlucides(?int $glucides): self { $this->glucides = $glucides; return $this; }

    public function getLipides(): ?int { return $this->lipides; }
    public function setLipides(?int $lipides): self { $this->lipides = $lipides; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    public function getRegime(): ?RegimeAlimentaire { return $this->regime; }
    public function setRegime(?RegimeAlimentaire $regime): self { $this->regime = $regime; return $this; }
}
