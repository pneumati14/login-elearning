<?php

namespace App\Entity;

use App\Repository\CustomerCardOrderRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One order placed for a [[CustomerCard]]: a catalogue product with a
 * mandatory quantity, the order date and a simple status. The product
 * name is snapshotted so the order survives catalogue edits or product
 * deletion (FK set null). Removed together with its card.
 */
#[ORM\Entity(repositoryClass: CustomerCardOrderRepository::class)]
#[ORM\Table(name: 'customer_card_order')]
#[ORM\Index(name: 'idx_customer_card_order_card', columns: ['card_id'])]
class CustomerCardOrder
{
    public const STATUS_QUOTE = 'quote';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PROFORMA = 'proforma';
    public const STATUS_PROFORMA_PAID = 'proforma_paid';
    public const STATUS_PROCUREMENT = 'procurement';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_RECEIVED = 'received';

    /**
     * Workflow order: quote → ordered → proforma → proforma paid →
     * procurement → shipping → received.
     */
    public const STATUSES = [
        self::STATUS_QUOTE,
        self::STATUS_ORDERED,
        self::STATUS_PROFORMA,
        self::STATUS_PROFORMA_PAID,
        self::STATUS_PROCUREMENT,
        self::STATUS_SHIPPING,
        self::STATUS_RECEIVED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomerCard::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CustomerCard $card;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    /** Product name snapshot at order time. */
    #[ORM\Column(length: 255)]
    private string $productName = '';

    #[ORM\Column]
    private int $quantity = 1;

    /** Per-piece purchase price (what we pay the supplier). */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $unitPurchasePrice = null;

    /** Per-piece sale price (what the customer pays). */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $unitSalePrice = null;

    #[ORM\Column(length: 3, options: ['default' => 'HUF'])]
    private string $currency = 'HUF';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $orderedAt;

    /** One of STATUS_*. */
    #[ORM\Column(length: 16, options: ['default' => self::STATUS_QUOTE])]
    private string $status = self::STATUS_QUOTE;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->orderedAt = new \DateTimeImmutable('today');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCard(): CustomerCard
    {
        return $this->card;
    }

    public function setCard(CustomerCard $card): static
    {
        $this->card = $card;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);

        return $this;
    }

    public function getUnitPurchasePrice(): ?string
    {
        return $this->unitPurchasePrice;
    }

    public function setUnitPurchasePrice(?string $price): static
    {
        $this->unitPurchasePrice = $price;

        return $this;
    }

    public function getUnitSalePrice(): ?string
    {
        return $this->unitSalePrice;
    }

    public function setUnitSalePrice(?string $price): static
    {
        $this->unitSalePrice = $price;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper(trim($currency));
        $this->currency = \in_array($currency, Product::CURRENCIES, true) ? $currency : Product::DEFAULT_CURRENCY;

        return $this;
    }

    public function getOrderedAt(): \DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(\DateTimeImmutable $orderedAt): static
    {
        $this->orderedAt = $orderedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = \in_array($status, self::STATUSES, true) ? $status : self::STATUS_QUOTE;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
