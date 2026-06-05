<?php

namespace App\Entity;

use App\Repository\BillingItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One line to invoice on the billing board. Created automatically when an
 * [[Opportunity]] enters a won stage (every quote line becomes a billing
 * item; a lineless deal becomes a single item from its title and value)
 * and when a [[CustomerCardOrder]] reaches the received status.
 * The name, quantity and unit price are snapshotted, so later edits to the
 * deal do not rewrite what was agreed; the rows themselves stay editable
 * here. Removed with the customer (onDelete CASCADE) but kept when the
 * source opportunity or card order is deleted (FK set null).
 */
#[ORM\Entity(repositoryClass: BillingItemRepository::class)]
#[ORM\Table(name: 'billing_item')]
#[ORM\Index(name: 'idx_billing_item_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_billing_item_opportunity', columns: ['opportunity_id'])]
#[ORM\Index(name: 'idx_billing_item_card_order', columns: ['card_order_id'])]
class BillingItem
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_INVOICED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** The won deal this item was snapshotted from; null for manual rows. */
    #[ORM\ManyToOne(targetEntity: Opportunity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Opportunity $opportunity = null;

    /** Kept for display even after the opportunity row is deleted. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $opportunityTitle = null;

    /** The received card order this item was snapshotted from; null otherwise. */
    #[ORM\ManyToOne(targetEntity: CustomerCardOrder::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CustomerCardOrder $cardOrder = null;

    /** Card type label, kept for display even after the card is deleted. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cardName = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $quantity = '1';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $unitPrice = '0';

    #[ORM\Column(length: 3)]
    private string $currency = Opportunity::DEFAULT_CURRENCY;

    #[ORM\Column(length: 16, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    /** When the source deal was won; manual rows use their creation day. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $wonAt = null;

    /** Set when the item is marked invoiced; cleared when reopened. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $invoicedAt = null;

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

    public function getOpportunity(): ?Opportunity
    {
        return $this->opportunity;
    }

    public function setOpportunity(?Opportunity $opportunity): static
    {
        $this->opportunity = $opportunity;
        $this->opportunityTitle = $opportunity?->getTitle();

        return $this;
    }

    public function getOpportunityTitle(): ?string
    {
        return $this->opportunityTitle;
    }

    public function getCardOrder(): ?CustomerCardOrder
    {
        return $this->cardOrder;
    }

    public function setCardOrder(?CustomerCardOrder $cardOrder): static
    {
        $this->cardOrder = $cardOrder;
        $this->cardName = $cardOrder?->getCard()->getType();

        return $this;
    }

    public function getCardName(): ?string
    {
        return $this->cardName;
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

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /** quantity × unitPrice, as a 2-decimal string. */
    public function getLineTotal(): string
    {
        return number_format((float) $this->quantity * (float) $this->unitPrice, 2, '.', '');
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper(trim($currency));
        $this->currency = \in_array($currency, Opportunity::CURRENCIES, true) ? $currency : Opportunity::DEFAULT_CURRENCY;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** Keeps invoicedAt in sync: stamped on invoiced, cleared on pending. */
    public function setStatus(string $status): static
    {
        if (!\in_array($status, self::STATUSES, true)) {
            return $this;
        }
        $this->status = $status;
        if (self::STATUS_INVOICED === $status) {
            $this->invoicedAt ??= new \DateTimeImmutable('today');
        } else {
            $this->invoicedAt = null;
        }

        return $this;
    }

    public function getWonAt(): ?\DateTimeImmutable
    {
        return $this->wonAt;
    }

    public function setWonAt(?\DateTimeImmutable $wonAt): static
    {
        $this->wonAt = $wonAt;

        return $this;
    }

    public function getInvoicedAt(): ?\DateTimeImmutable
    {
        return $this->invoicedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
