<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'supplement_order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Supplement::class)]
    #[ORM\JoinColumn(name: 'supplement_id', nullable: false)]
    private ?Supplement $supplement = null;

    #[ORM\Column(name: 'supplement_name', length: 255, nullable: true)]
    private ?string $supplementName = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(name: 'price_at_order', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $priceAtOrder = '0.00';

    #[ORM\Column(name: 'unit_price', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private string $price = '0.00';

    #[ORM\Column(name: 'line_total', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private string $total = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }

    public function getSupplement(): ?Supplement
    {
        return $this->supplement;
    }

    public function setSupplement(?Supplement $supplement): static
    {
        $this->supplement = $supplement;
        $this->supplementName = $supplement?->getName();
        return $this;
    }

    public function getSupplementName(): ?string
    {
        return $this->supplementName;
    }

    public function setSupplementName(?string $supplementName): static
    {
        $this->supplementName = $supplementName;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        $this->priceAtOrder = $price;
        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getPriceAtOrder(): string
    {
        return $this->priceAtOrder;
    }

    public function setPriceAtOrder(string $priceAtOrder): static
    {
        $this->priceAtOrder = $priceAtOrder;
        return $this;
    }
}
