<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'regime_alimentaire')]
class RegimeAlimentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $taille = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $poids = null;

    #[ORM\Column(nullable: true)]
    private ?int $age = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $bmi = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeSante = null;

    #[ORM\Column(nullable: true)]
    private ?int $caloriesCibles = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $repasAdequats = null;

    #[ORM\OneToMany(mappedBy: 'regime', targetEntity: Repas::class)]
    private Collection $repas;

    public function __construct()
    {
        $this->repas = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getTaille(): ?float
    {
        return $this->taille !== null ? (float) $this->taille : null;
    }

    public function setTaille(?float $taille): self
    {
        $this->taille = $taille !== null ? (string) $taille : null;
        return $this;
    }

    public function getPoids(): ?float
    {
        return $this->poids !== null ? (float) $this->poids : null;
    }

    public function setPoids(?float $poids): self
    {
        $this->poids = $poids !== null ? (string) $poids : null;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getBmi(): ?float
    {
        return $this->bmi !== null ? (float) $this->bmi : null;
    }

    public function setBmi(?float $bmi): self
    {
        $this->bmi = $bmi !== null ? (string) $bmi : null;
        return $this;
    }

    public function getTypeSante(): ?string
    {
        return $this->typeSante;
    }

    public function setTypeSante(?string $typeSante): self
    {
        $this->typeSante = $typeSante;
        return $this;
    }

    public function getCaloriesCibles(): ?int
    {
        return $this->caloriesCibles;
    }

    public function setCaloriesCibles(?int $caloriesCibles): self
    {
        $this->caloriesCibles = $caloriesCibles;
        return $this;
    }

    public function getRepasAdequats(): ?string
    {
        return $this->repasAdequats;
    }

    public function setRepasAdequats(?string $repasAdequats): self
    {
        $this->repasAdequats = $repasAdequats;
        return $this;
    }

    /**
     * @return Collection<int, Repas>
     */
    public function getRepas(): Collection
    {
        return $this->repas;
    }

    public function addRepa(Repas $repa): self
    {
        if (!$this->repas->contains($repa)) {
            $this->repas->add($repa);
            $repa->setRegime($this);
        }

        return $this;
    }

    public function removeRepa(Repas $repa): self
    {
        if ($this->repas->removeElement($repa)) {
            if ($repa->getRegime() === $this) {
                $repa->setRegime(null);
            }
        }

        return $this;
    }
}
