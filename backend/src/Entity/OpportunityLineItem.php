<?php

namespace App\Entity;

use App\Repository\OpportunityLineItemRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One priced line on an [[Opportunity]] (a quote line). Optionally linked
 * to a catalogue [[Product]], but the name and unit price are snapshotted
 * here so the line survives catalogue edits or product deletion (the
 * product FK is set null on delete). Removed together with its
 * opportunity (onDelete CASCADE). The currency is the opportunity's — a
 * single deal does not mix currencies.
 */
#[ORM\Entity(repositoryClass: OpportunityLineItemRepository::class)]
#[ORM\Table(name: 'opportunity_line_item')]
#[ORM\Index(name: 'idx_opp_line_item_opportunity', columns: ['opportunity_id'])]
class OpportunityLineItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'lineItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Opportunity $opportunity;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $productName = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $quantity = '1';

    #[ORM\Column(type: 'decimal', precision: 14, scale: 2)]
    private string $unitPrice = '0';

    /** Display order within the opportunity. */
    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpportunity(): Opportunity
    {
        return $this->opportunity;
    }

    public function setOpportunity(Opportunity $opportunity): static
    {
        $this->opportunity = $opportunity;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /** quantity × unitPrice, as a 2-decimal string. */
    public function getLineTotal(): string
    {
        return number_format((float) $this->quantity * (float) $this->unitPrice, 2, '.', '');
    }
}
