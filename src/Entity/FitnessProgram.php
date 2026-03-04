<?php

namespace App\Entity;

use App\Repository\FitnessProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FitnessProgramRepository::class)]
#[ORM\Table(name: 'fitness_program')]
#[ORM\HasLifecycleCallbacks]
class FitnessProgram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private string $category = '';

    #[ORM\Column(length: 50)]
    private string $level = '';

    #[ORM\Column]
    private int $durationWeeks = 4;

    #[ORM\Column]
    private int $sessionsPerWeek = 3;

    #[ORM\Column]
    private int $sessionDuration = 45;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** @var Collection<int, FitnessExercise> */
    #[ORM\ManyToMany(targetEntity: FitnessExercise::class, inversedBy: 'programs')]
    #[ORM\JoinTable(name: 'fitness_program_exercise')]
    private Collection $exercises;

    /** @var Collection<int, FitnessPlan> */
    #[ORM\OneToMany(mappedBy: 'program', targetEntity: FitnessPlan::class)]
    private Collection $plans;

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
        $this->plans = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getDurationWeeks(): int
    {
        return $this->durationWeeks;
    }

    public function setDurationWeeks(int $durationWeeks): static
    {
        $this->durationWeeks = $durationWeeks;
        return $this;
    }

    public function getSessionsPerWeek(): int
    {
        return $this->sessionsPerWeek;
    }

    public function setSessionsPerWeek(int $sessionsPerWeek): static
    {
        $this->sessionsPerWeek = $sessionsPerWeek;
        return $this;
    }

    public function getSessionDuration(): int
    {
        return $this->sessionDuration;
    }

    public function setSessionDuration(int $sessionDuration): static
    {
        $this->sessionDuration = $sessionDuration;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): static
    {
        $this->videoUrl = $videoUrl;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /** @return Collection<int, FitnessExercise> */
    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function addExercise(FitnessExercise $exercise): static
    {
        if (!$this->exercises->contains($exercise)) {
            $this->exercises->add($exercise);
        }
        return $this;
    }

    public function removeExercise(FitnessExercise $exercise): static
    {
        $this->exercises->removeElement($exercise);
        return $this;
    }

    /** @return Collection<int, FitnessPlan> */
    public function getPlans(): Collection
    {
        return $this->plans;
    }

    public function addPlan(FitnessPlan $plan): static
    {
        if (!$this->plans->contains($plan)) {
            $this->plans->add($plan);
            $plan->setProgram($this);
        }

        return $this;
    }

    public function removePlan(FitnessPlan $plan): static
    {
        if ($this->plans->removeElement($plan) && $plan->getProgram() === $this) {
            $plan->setProgram(null);
        }

        return $this;
    }
}
