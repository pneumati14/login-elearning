<?php

namespace App\Entity;

use App\Repository\OpportunityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A sales opportunity (deal) for a [[Customer]]. Sub-resource of the
 * customer (onDelete CASCADE, hard delete, like [[Contact]]). Each
 * opportunity belongs to one [[OpportunityType]] (the pipeline) and sits
 * in one of that type's [[OpportunityStage]]s. The responsible
 * salesperson is not stored here — it is derived from the customer's
 * current [[CustomerSalesAssignment]].
 */
#[ORM\Entity(repositoryClass: OpportunityRepository::class)]
#[ORM\Table(name: 'opportunity')]
#[ORM\Index(name: 'idx_opportunity_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_opportunity_type', columns: ['type_id'])]
#[ORM\Index(name: 'idx_opportunity_stage', columns: ['stage_id'])]
class Opportunity
{
    public const CURRENCIES = ['HUF', 'EUR', 'USD'];
    public const DEFAULT_CURRENCY = 'HUF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'opportunities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** The pipeline this opportunity runs through. Fixed after creation. */
    #[ORM\ManyToOne(targetEntity: OpportunityType::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private OpportunityType $type;

    /** Current stage. Must belong to {@see $type}. */
    #[ORM\ManyToOne(targetEntity: OpportunityStage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private OpportunityStage $stage;

    #[ORM\Column(length: 255)]
    private string $title = '';

    /** Manually entered offer/quote number. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $quoteNumber = null;

    /** Estimated deal value; null when unknown. Stored as a decimal string. */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 3)]
    private string $currency = self::DEFAULT_CURRENCY;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedCloseDate = null;

    /** Set automatically when the opportunity enters a won/lost stage. */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** Optional contact person at the customer for this deal. */
    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    /**
     * Fulfillment category once the deal is won (hardware / software /
     * development…). Null until the won deal is categorised on the
     * fulfillment board.
     */
    #[ORM\ManyToOne(targetEntity: FulfillmentType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FulfillmentType $fulfillmentType = null;

    /** Current delivery stage; must belong to {@see $fulfillmentType}. */
    #[ORM\ManyToOne(targetEntity: FulfillmentStage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FulfillmentStage $fulfillmentStage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Chronological log of stage transitions. Oldest first.
     *
     * @var Collection<int, OpportunityStageChange>
     */
    #[ORM\OneToMany(mappedBy: 'opportunity', targetEntity: OpportunityStageChange::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['changedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $stageChanges;

    /**
     * Priced quote lines. The opportunity's value is the sum of these when
     * any exist.
     *
     * @var Collection<int, OpportunityLineItem>
     */
    #[ORM\OneToMany(mappedBy: 'opportunity', targetEntity: OpportunityLineItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $lineItems;

    /**
     * Uploaded offer/quote documents (PDFs). Newest first.
     *
     * @var Collection<int, OpportunityDocument>
     */
    #[ORM\OneToMany(mappedBy: 'opportunity', targetEntity: OpportunityDocument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['uploadedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $documents;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->stageChanges = new ArrayCollection();
        $this->lineItems = new ArrayCollection();
        $this->documents = new ArrayCollection();
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

    public function getType(): OpportunityType
    {
        return $this->type;
    }

    public function setType(OpportunityType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStage(): OpportunityStage
    {
        return $this->stage;
    }

    public function setStage(OpportunityStage $stage): static
    {
        $this->stage = $stage;
        // Keep closedAt in sync with the stage's outcome.
        if (OpportunityStage::OUTCOME_OPEN === $stage->getOutcome()) {
            $this->closedAt = null;
        } elseif (null === $this->closedAt) {
            $this->closedAt = new \DateTimeImmutable();
        }

        return $this;
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

    public function getQuoteNumber(): ?string
    {
        return $this->quoteNumber;
    }

    public function setQuoteNumber(?string $quoteNumber): static
    {
        $this->quoteNumber = $quoteNumber;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $currency = strtoupper(trim($currency));
        $this->currency = \in_array($currency, self::CURRENCIES, true) ? $currency : self::DEFAULT_CURRENCY;

        return $this;
    }

    public function getExpectedCloseDate(): ?\DateTimeImmutable
    {
        return $this->expectedCloseDate;
    }

    public function setExpectedCloseDate(?\DateTimeImmutable $expectedCloseDate): static
    {
        $this->expectedCloseDate = $expectedCloseDate;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getFulfillmentType(): ?FulfillmentType
    {
        return $this->fulfillmentType;
    }

    public function setFulfillmentType(?FulfillmentType $type): static
    {
        $this->fulfillmentType = $type;

        return $this;
    }

    public function getFulfillmentStage(): ?FulfillmentStage
    {
        return $this->fulfillmentStage;
    }

    public function setFulfillmentStage(?FulfillmentStage $stage): static
    {
        $this->fulfillmentStage = $stage;

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
     * @return Collection<int, OpportunityStageChange>
     */
    public function getStageChanges(): Collection
    {
        return $this->stageChanges;
    }

    public function addStageChange(OpportunityStageChange $change): static
    {
        if (!$this->stageChanges->contains($change)) {
            $this->stageChanges->add($change);
            $change->setOpportunity($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, OpportunityLineItem>
     */
    public function getLineItems(): Collection
    {
        return $this->lineItems;
    }

    public function addLineItem(OpportunityLineItem $item): static
    {
        if (!$this->lineItems->contains($item)) {
            $this->lineItems->add($item);
            $item->setOpportunity($this);
        }

        return $this;
    }

    public function clearLineItems(): void
    {
        $this->lineItems->clear();
    }

    /** Sum of all line totals, as a 2-decimal string. */
    public function getLineItemsTotal(): string
    {
        $total = 0.0;
        foreach ($this->lineItems as $item) {
            $total += (float) $item->getLineTotal();
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @return Collection<int, OpportunityDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(OpportunityDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setOpportunity($this);
        }

        return $this;
    }

    public function removeDocument(OpportunityDocument $document): static
    {
        $this->documents->removeElement($document);

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
