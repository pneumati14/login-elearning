<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A catalogue product/service that can be added as a line item to an
 * [[Opportunity]]. Admin-managed config — plain fields, not localized,
 * like [[Customer]] and [[OpportunityType]]. Hard delete; existing
 * opportunity line items snapshot the name and price, so deleting a
 * product does not rewrite history (the line item's FK is set null).
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
class Product
{
    public const CURRENCIES = ['HUF', 'EUR', 'USD'];
    public const DEFAULT_CURRENCY = 'HUF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Optional stock-keeping / article number. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sku = null;

    /**
     * Top-level category (Hardver / Szoftver). Required in the form, but
     * nullable in the DB so existing rows and a deleted category (SET
     * NULL) do not break.
     */
    #[ORM\ManyToOne(targetEntity: ProductCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductCategory $category = null;

    /** Optional sub-category; must belong to the chosen category. */
    #[ORM\ManyToOne(targetEntity: ProductSubcategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductSubcategory $subcategory = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $unitPrice = null;

    /**
     * Material part of the unit price, used only when the product's
     * category has split pricing. The plain unitPrice is then the sum of
     * this and feeUnitPrice (null part counts as 0).
     */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $materialUnitPrice = null;

    /** Fee/labour part of the unit price (split-pricing categories only). */
    #[ORM\Column(type: 'decimal', precision: 14, scale: 2, nullable: true)]
    private ?string $feeUnitPrice = null;

    #[ORM\Column(length: 3)]
    private string $currency = self::DEFAULT_CURRENCY;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSubcategory(): ?ProductSubcategory
    {
        return $this->subcategory;
    }

    public function setSubcategory(?ProductSubcategory $subcategory): static
    {
        $this->subcategory = $subcategory;

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

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getMaterialUnitPrice(): ?string
    {
        return $this->materialUnitPrice;
    }

    public function setMaterialUnitPrice(?string $materialUnitPrice): static
    {
        $this->materialUnitPrice = $materialUnitPrice;

        return $this;
    }

    public function getFeeUnitPrice(): ?string
    {
        return $this->feeUnitPrice;
    }

    public function setFeeUnitPrice(?string $feeUnitPrice): static
    {
        $this->feeUnitPrice = $feeUnitPrice;

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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
