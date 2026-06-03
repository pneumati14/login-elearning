<?php

namespace App\Entity;

use App\Repository\OpportunityTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A named sales pipeline (e.g. "New sale", "Renewal"). Administrators
 * configure types and their ordered [[OpportunityStage]] list; an
 * Opportunity then belongs to one type and sits in one of its stages.
 * Plain admin-managed config — not localized.
 */
#[ORM\Entity(repositoryClass: OpportunityTypeRepository::class)]
#[ORM\Table(name: 'opportunity_type')]
class OpportunityType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Display order among types. */
    #[ORM\Column]
    private int $position = 0;

    /** Inactive types stay for history but are hidden from new opportunities. */
    #[ORM\Column]
    private bool $isActive = true;

    /**
     * Optional validity window. A type whose window has not started yet
     * (validFrom in the future) or has ended (validUntil in the past) is
     * treated as not currently usable, on top of the manual isActive flag.
     */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    /**
     * Ordered pipeline stages. Sorted by position.
     *
     * @var Collection<int, OpportunityStage>
     */
    #[ORM\OneToMany(mappedBy: 'type', targetEntity: OpportunityStage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $stages;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->stages = new ArrayCollection();
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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

    /**
     * @return Collection<int, OpportunityStage>
     */
    public function getStages(): Collection
    {
        return $this->stages;
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
