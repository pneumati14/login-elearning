<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A business customer managed by administrators. Fields are plain (not
 * localized): customer names and billing data are legal records that
 * don't get translated. Soft-deleted via [[deletedAt]].
 */
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customer')]
#[ORM\Index(name: 'idx_customer_deleted_at', columns: ['deleted_at'])]
class Customer
{
    public const STATUS_EXISTING = 'existing';
    public const STATUS_POTENTIAL = 'potential';

    public const STATUSES = [self::STATUS_EXISTING, self::STATUS_POTENTIAL];

    public const CURRENCIES = ['HUF', 'EUR', 'USD'];
    public const DEFAULT_CURRENCY = 'HUF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** One of STATUS_*: an existing (paying) or a potential customer. */
    #[ORM\Column(length: 16, options: ['default' => self::STATUS_POTENTIAL])]
    private string $status = self::STATUS_POTENTIAL;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    private Address $address;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'billing_address_')]
    private Address $billingAddress;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * History of salesperson assignments. The "current" owner is derived
     * from the entries whose period covers today.
     *
     * @var Collection<int, CustomerSalesAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerSalesAssignment::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $salesAssignments;

    /**
     * People at this customer company. Removed together with the customer.
     *
     * @var Collection<int, Contact>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Contact::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $contacts;

    /**
     * Recurring monthly fee items with validity periods. The current
     * monthly fee is the sum of the items active today; price changes
     * keep history (old item closed, new one opened). Ordered oldest
     * first by start date.
     *
     * @var Collection<int, CustomerFeeItem>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerFeeItem::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['validFrom' => 'ASC', 'id' => 'ASC'])]
    private Collection $feeItems;

    /**
     * Sales opportunities (deals) for this customer. Removed with the customer.
     *
     * @var Collection<int, Opportunity>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Opportunity::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $opportunities;

    /**
     * Logged activities (calls, meetings, notes, tasks…). Removed with the customer.
     *
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: Activity::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $activities;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    /** Set to mark the customer as deleted; listings filter on IS NULL. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->address = new Address();
        $this->billingAddress = new Address();
        $this->salesAssignments = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->feeItems = new ArrayCollection();
        $this->opportunities = new ArrayCollection();
        $this->activities = new ArrayCollection();
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = \in_array($status, self::STATUSES, true) ? $status : self::STATUS_POTENTIAL;

        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getBillingAddress(): Address
    {
        return $this->billingAddress;
    }

    public function getTaxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function setTaxNumber(?string $taxNumber): static
    {
        $this->taxNumber = $taxNumber;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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

    /**
     * @return Collection<int, CustomerSalesAssignment>
     */
    public function getSalesAssignments(): Collection
    {
        return $this->salesAssignments;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    /**
     * @return Collection<int, CustomerFeeItem>
     */
    public function getFeeItems(): Collection
    {
        return $this->feeItems;
    }

    /**
     * Per-currency sum of the fee items active on the given date,
     * e.g. ['HUF' => '125000.00']. Currencies are never converted.
     *
     * @return array<string, string>
     */
    public function monthlyFeeTotals(\DateTimeImmutable $date): array
    {
        $totals = [];
        foreach ($this->feeItems as $item) {
            if (!$item->isActiveOn($date)) {
                continue;
            }
            $totals[$item->getCurrency()] = ($totals[$item->getCurrency()] ?? 0.0) + (float) $item->getAmount();
        }
        ksort($totals);

        return array_map(fn (float $sum): string => number_format($sum, 2, '.', ''), $totals);
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public function getOpportunities(): Collection
    {
        return $this->opportunities;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

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

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
