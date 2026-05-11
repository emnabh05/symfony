<?php

namespace App\Entity;

use App\Repository\FitnessExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FitnessExerciseRepository::class)]
#[ORM\Table(name: 'fitness_exercise')]
#[ORM\HasLifecycleCallbacks]
class FitnessExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'muscle_group', length: 100)]
    private string $muscleGroup = '';

    #[ORM\Column(length: 50)]
    private string $difficulty = '';

    #[ORM\Column(name: 'sets_count')]
    private int $sets = 0;

    #[ORM\Column]
    private int $repetitions = 0;

    #[ORM\Column(name: 'video_url', length: 500, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(name: 'image_url', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private int $duration = 0;

    #[ORM\Column(length: 20, options: ['default' => 'both'])]
    private string $place = 'both';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    private ?User $user = null;

    /** @var Collection<int, FitnessProgram> */
    #[ORM\ManyToMany(targetEntity: FitnessProgram::class, mappedBy: 'exercises')]
    private Collection $programs;

    public function __construct()
    {
        $this->programs = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getMuscleGroup(): string
    {
        return $this->muscleGroup;
    }

    public function setMuscleGroup(string $muscleGroup): static
    {
        $this->muscleGroup = $muscleGroup;
        return $this;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getSets(): int
    {
        return $this->sets;
    }

    public function setSets(?int $sets): static
    {
        $this->sets = max(0, (int) ($sets ?? 0));
        return $this;
    }

    public function getRepetitions(): int
    {
        return $this->repetitions;
    }

    public function setRepetitions(?int $repetitions): static
    {
        $this->repetitions = max(0, (int) ($repetitions ?? 0));
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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = max(0, (int) ($duration ?? 0));
        return $this;
    }

    public function getPlace(): string
    {
        return $this->place;
    }

    public function setPlace(?string $place): static
    {
        $normalized = strtolower(trim((string) $place));
        $this->place = match ($normalized) {
            'gym', 'salle' => 'gym',
            'home', 'maison' => 'home',
            default => 'both',
        };

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

    /** @return Collection<int, FitnessProgram> */
    public function getPrograms(): Collection
    {
        return $this->programs;
    }

    public function addProgram(FitnessProgram $program): static
    {
        if (!$this->programs->contains($program)) {
            $this->programs->add($program);
        }

        return $this;
    }

    public function removeProgram(FitnessProgram $program): static
    {
        $this->programs->removeElement($program);
        return $this;
    }
}
