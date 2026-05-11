<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'supplement_orders')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ON_WAY = 'on_way';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELED = 'canceled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    private ?string $orderNumberOverride = null;

    #[ORM\Column(name: 'first_name', length: 255, nullable: true)]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', length: 255, nullable: true)]
    private string $lastName = '';

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(length: 50, nullable: true)]
    private string $phone = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private string $address = '';

    #[ORM\Column(length: 100, nullable: true)]
    private string $city = '';

    #[ORM\Column(name: 'postal_code', length: 20, nullable: true)]
    private string $postalCode = '';

    #[ORM\Column(name: 'payment_method', length: 50, nullable: true)]
    private string $paymentMethod = '';

    #[ORM\Column(length: 50, nullable: true)]
    private string $status = 'PLACED';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\Column(name: 'shipping_cost', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $shipping = '0.00';

    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discount = '0.00';

    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(name: 'discount_code', length: 50, nullable: true)]
    private ?string $discountCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderItems;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        if ($this->orderNumberOverride !== null && $this->orderNumberOverride !== '') {
            return $this->orderNumberOverride;
        }

        if ($this->id === null) {
            return 'ORD-PENDING';
        }

        return sprintf('ORD-%06d', $this->id);
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $normalized = trim($orderNumber);
        $this->orderNumberOverride = $normalized !== '' ? $normalized : null;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $normalized = strtolower(trim($paymentMethod));
        $this->paymentMethod = match ($normalized) {
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'paypal' => 'PayPal',
            'cod', 'cash on delivery' => 'Cash on Delivery',
            default => trim($paymentMethod) !== '' ? trim($paymentMethod) : 'Unknown',
        };
        return $this;
    }

    public function getStatus(): string
    {
        return self::normalizeStatusForDisplay($this->status);
    }

    public function getRawStatus(): string
    {
        return self::normalizeStatusForStorage($this->status);
    }

    public function setStatus(string $status): static
    {
        $this->status = self::normalizeStatusForStorage($status);
        return $this;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    public function getShipping(): string
    {
        return $this->shipping;
    }

    public function setShipping(string $shipping): static
    {
        $this->shipping = $shipping;
        return $this;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): static
    {
        $this->discount = $discount;
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

    public function getDiscountCode(): ?string
    {
        return $this->discountCode;
    }

    public function setDiscountCode(?string $discountCode): static
    {
        $this->discountCode = $discountCode;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
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

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return list<string>
     */
    public static function getCanceledStorageStatuses(): array
    {
        return ['CANCELED', 'CANCELLED', 'canceled', 'cancelled'];
    }

    public static function normalizeStatusForStorage(?string $status): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '_', trim((string) $status)));

        return match ($normalized) {
            '', 'PENDING', 'PLACED' => 'PLACED',
            'ACCEPTED', 'PROCESSING', 'ON_PROGRESS' => 'ON_PROGRESS',
            'ON_WAY', 'ON_THE_WAY' => 'ON_THE_WAY',
            'DELIVERED' => 'DELIVERED',
            'CANCELED', 'CANCELLED' => 'CANCELED',
            default => $normalized,
        };
    }

    public static function normalizeStatusForDisplay(?string $status): string
    {
        return match (self::normalizeStatusForStorage($status)) {
            'PLACED' => self::STATUS_PENDING,
            'ON_PROGRESS' => self::STATUS_PROCESSING,
            'ON_THE_WAY' => self::STATUS_ON_WAY,
            'DELIVERED' => self::STATUS_DELIVERED,
            'CANCELED' => self::STATUS_CANCELED,
            default => strtolower(str_replace(' ', '_', (string) $status)),
        };
    }
}
