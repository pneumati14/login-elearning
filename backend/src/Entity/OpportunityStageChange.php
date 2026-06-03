<?php

namespace App\Entity;

use App\Repository\OpportunityStageChangeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One entry in an [[Opportunity]]'s stage-transition history. Records the
 * move from one stage to another, who made it and when. Stage names are
 * snapshotted (not FK references) so the history survives stage renames
 * or deletions. Deleted together with its opportunity (onDelete CASCADE).
 */
#[ORM\Entity(repositoryClass: OpportunityStageChangeRepository::class)]
#[ORM\Table(name: 'opportunity_stage_change')]
#[ORM\Index(name: 'idx_opp_stage_change_opportunity', columns: ['opportunity_id'])]
class OpportunityStageChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'stageChanges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Opportunity $opportunity;

    /** Stage moved away from; null for the initial entry on creation. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromStageName = null;

    #[ORM\Column(length: 255)]
    private string $toStageName = '';

    /** The admin who made the change; null if that user was later removed. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

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

    public function getFromStageName(): ?string
    {
        return $this->fromStageName;
    }

    public function setFromStageName(?string $fromStageName): static
    {
        $this->fromStageName = $fromStageName;

        return $this;
    }

    public function getToStageName(): string
    {
        return $this->toStageName;
    }

    public function setToStageName(string $toStageName): static
    {
        $this->toStageName = $toStageName;

        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }
}
