<?php

namespace App\Entity;

use App\Repository\CustomerFeeItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One recurring monthly fee item of a [[Customer]] (e.g. a licensed
 * module), valid for a period. Price changes are modelled as history:
 * the old item is closed (validUntil) and a new one starts
 * (validFrom), so past fees stay auditable. The customer's current
 * monthly fee is the sum of the items active today. Hard-deleted with
 * the customer (onDelete CASCADE).
 */
#[ORM\Entity(repositoryClass: CustomerFeeItemRepository::class)]
#[ORM\Table(name: 'customer_fee_item')]
#[ORM\Index(name: 'idx_customer_fee_item_customer', columns: ['customer_id'])]
class CustomerFeeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'feeItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /**
     * Optional catalogue product this fee is based on. The name and the
     * amount live on the item itself (prefilled from the product,
     * freely overridable), so the fee survives catalogue edits or
     * product deletion (FK set null).
     */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /**
     * Headcount-based pricing: the effective amount is
     * unitAmount × quantity instead of a flat amount.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isPerHead = false;

    /** Per-head unit price; only meaningful when isPerHead. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $unitAmount = null;

    /** Headcount; only meaningful when isPerHead. */
    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    /** Effective monthly total — for per-head items unitAmount × quantity. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = Customer::DEFAULT_CURRENCY;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isPerHead(): bool
    {
        return $this->isPerHead;
    }

    public function setIsPerHead(bool $isPerHead): static
    {
        $this->isPerHead = $isPerHead;

        return $this;
    }

    public function getUnitAmount(): ?string
    {
        return $this->unitAmount;
    }

    public function setUnitAmount(?string $unitAmount): static
    {
        $this->unitAmount = $unitAmount;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /** Recompute the stored total from unit price × headcount. */
    public function recomputePerHeadAmount(): void
    {
        if ($this->isPerHead) {
            $this->amount = number_format((float) ($this->unitAmount ?? 0) * (int) ($this->quantity ?? 0), 2, '.', '');
        }
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper(trim($currency));
        $this->currency = \in_array($currency, Customer::CURRENCIES, true) ? $currency : Customer::DEFAULT_CURRENCY;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Is this fee item in force on the given date? */
    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        $day = $date->setTime(0, 0);
        if (null !== $this->validFrom && $this->validFrom > $day) {
            return false;
        }
        if (null !== $this->validUntil && $this->validUntil < $day) {
            return false;
        }

        return true;
    }
}
