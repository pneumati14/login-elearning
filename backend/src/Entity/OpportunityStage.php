<?php

namespace App\Entity;

use App\Repository\OpportunityStageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One ordered step in an [[OpportunityType]]'s pipeline. The `outcome`
 * marks terminal stages so reporting and colouring can tell open from
 * won/lost. Deleted together with its type via onDelete CASCADE.
 */
#[ORM\Entity(repositoryClass: OpportunityStageRepository::class)]
#[ORM\Table(name: 'opportunity_stage')]
#[ORM\Index(name: 'idx_opportunity_stage_type', columns: ['type_id'])]
class OpportunityStage
{
    public const OUTCOME_OPEN = 'open';
    public const OUTCOME_WON = 'won';
    public const OUTCOME_LOST = 'lost';

    public const OUTCOMES = [self::OUTCOME_OPEN, self::OUTCOME_WON, self::OUTCOME_LOST];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OpportunityType::class, inversedBy: 'stages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OpportunityType $type;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Order within the pipeline. */
    #[ORM\Column]
    private int $position = 0;

    /** One of OUTCOME_*: open (in progress), won, or lost (terminal). */
    #[ORM\Column(length: 16)]
    private string $outcome = self::OUTCOME_OPEN;

    /**
     * Win probability (%) used to weight deal values in the forecast.
     * Fixed for terminal stages: won = 100, lost = 0.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $probability = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function setOutcome(string $outcome): static
    {
        $this->outcome = \in_array($outcome, self::OUTCOMES, true) ? $outcome : self::OUTCOME_OPEN;
        // Terminal stages have a fixed probability.
        if (self::OUTCOME_WON === $this->outcome) {
            $this->probability = 100;
        } elseif (self::OUTCOME_LOST === $this->outcome) {
            $this->probability = 0;
        }

        return $this;
    }

    public function getProbability(): int
    {
        return $this->probability;
    }

    /** Clamped to 0–100; ignored on terminal stages (their value is fixed). */
    public function setProbability(int $probability): static
    {
        if (self::OUTCOME_OPEN === $this->outcome) {
            $this->probability = max(0, min(100, $probability));
        }

        return $this;
    }
}
