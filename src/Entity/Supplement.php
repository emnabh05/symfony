<?php

namespace App\Entity;

use App\Repository\SupplementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplementRepository::class)]
#[ORM\Table(name: 'supplements')]
#[ORM\HasLifecycleCallbacks]
class Supplement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Product name is required.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Product name must be at least {{ limit }} characters.',
        maxMessage: 'Product name must not exceed {{ limit }} characters.'
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\\s\\-'.&,\\/()+:%#]+$/",
        message: 'Product name contains invalid characters.'
    )]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Category is required.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Category must not exceed {{ limit }} characters.'
    )]
    private string $category = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Brand is required.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Brand must not exceed {{ limit }} characters.'
    )]
    private string $brand = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Price is required.')]
    #[Assert\Regex(
        pattern: '/^\d+([.,]\d{1,2})?$/',
        message: 'Price must be a valid number with max 2 decimals.'
    )]
    #[Assert\GreaterThan(value: 0, message: 'Price must be greater than 0.')]
    #[Assert\LessThanOrEqual(value: 9999999.99, message: 'Price is too large.')]
    private string $price = '0.00';

    #[ORM\Column]
    #[Assert\NotNull(message: 'Stock quantity is required.')]
    #[Assert\PositiveOrZero(message: 'Stock must be a whole number greater than or equal to 0.')]
    private int $stock = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Calories must be a whole number greater than or equal to 0.')]
    private ?int $calories = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Description is required.')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'Description must be at least {{ limit }} characters.',
        maxMessage: 'Description must not exceed {{ limit }} characters.'
    )]
    private string $description = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'recommended_duration_days', options: ['default' => 30])]
    #[Assert\Positive(message: 'Recommended duration must be greater than 0 days.')]
    private int $recommendedDurationDays = 30;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(name: 'minimum_stock_threshold', options: ['default' => 10])]
    private int $minimumStockThreshold = 10;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = trim($category); return $this; }
    public function getBrand(): string { return $this->brand; }
    public function setBrand(string $brand): static { $this->brand = trim($brand); return $this; }
    public function getPrice(): string { return $this->price; }
    public function setPrice(string $price): static
    {
        $normalized = str_replace(',', '.', trim($price));
        $this->price = $normalized;
        return $this;
    }
    public function getStock(): int { return $this->stock; }
    public function setStock(int $stock): static { $this->stock = $stock; return $this; }
    public function getCalories(): ?int { return $this->calories; }
    public function setCalories(?int $calories): static { $this->calories = $calories; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = trim($description); return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getRecommendedDurationDays(): int { return $this->recommendedDurationDays; }
    public function setRecommendedDurationDays(int $recommendedDurationDays): static
    {
        $this->recommendedDurationDays = max(1, $recommendedDurationDays);
        return $this;
    }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function getManufacturer(): ?string { return $this->manufacturer; }
    public function setManufacturer(?string $manufacturer): static { $this->manufacturer = $manufacturer; return $this; }
    public function getMinimumStockThreshold(): int { return $this->minimumStockThreshold; }
    public function setMinimumStockThreshold(int $minimumStockThreshold): static
    {
        $this->minimumStockThreshold = max(0, $minimumStockThreshold);
        return $this;
    }
}
