<?php

namespace App\Entity;

use App\Repository\OpportunityEffortEstimateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One row of the preliminary effort estimate on an [[Opportunity]]: a
 * named piece of work with the expected effort of one kind (development
 * or project management), entered in days or hours. The unit is kept as
 * entered for faithful display; reporting converts hours to days with
 * {@see HOURS_PER_DAY}. Removed together with its opportunity (onDelete
 * CASCADE).
 */
#[ORM\Entity(repositoryClass: OpportunityEffortEstimateRepository::class)]
#[ORM\Table(name: 'opportunity_effort_estimate')]
#[ORM\Index(name: 'idx_opp_effort_opportunity', columns: ['opportunity_id'])]
class OpportunityEffortEstimate
{
    public const TYPE_DEVELOPMENT = 'development';
    public const TYPE_PM = 'pm';
    public const TYPES = [self::TYPE_DEVELOPMENT, self::TYPE_PM];

    public const UNIT_DAY = 'day';
    public const UNIT_HOUR = 'hour';
    public const UNITS = [self::UNIT_DAY, self::UNIT_HOUR];

    /** One working day equals this many hours when converting for totals. */
    public const HOURS_PER_DAY = 8;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'effortEstimates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Opportunity $opportunity;

    /** What the effort is for (e.g. "Backend API"). */
    #[ORM\Column(length: 255)]
    private string $name = '';

    /** One of {@see TYPES}. */
    #[ORM\Column(length: 16)]
    private string $effortType = self::TYPE_DEVELOPMENT;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    private string $amount = '0';

    /** One of {@see UNITS} — the unit the amount was entered in. */
    #[ORM\Column(length: 8)]
    private string $unit = self::UNIT_DAY;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEffortType(): string
    {
        return $this->effortType;
    }

    public function setEffortType(string $effortType): static
    {
        $this->effortType = \in_array($effortType, self::TYPES, true) ? $effortType : self::TYPE_DEVELOPMENT;

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

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = \in_array($unit, self::UNITS, true) ? $unit : self::UNIT_DAY;

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

    /** The amount converted to days, as a 2-decimal string. */
    public function getAmountDays(): string
    {
        $amount = (float) $this->amount;
        if (self::UNIT_HOUR === $this->unit) {
            $amount /= self::HOURS_PER_DAY;
        }

        return number_format($amount, 2, '.', '');
    }
}
