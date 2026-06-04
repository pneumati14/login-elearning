<?php

namespace App\Entity;

use App\Repository\FulfillmentStageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One ordered delivery step in a [[FulfillmentType]]'s process. Stages
 * flagged as "done" mark the delivery as completed (e.g. Átadva), so
 * the board can tell in-progress from finished projects. Deleted
 * together with its type via onDelete CASCADE.
 */
#[ORM\Entity(repositoryClass: FulfillmentStageRepository::class)]
#[ORM\Table(name: 'fulfillment_stage')]
#[ORM\Index(name: 'idx_fulfillment_stage_type', columns: ['type_id'])]
class FulfillmentStage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FulfillmentType::class, inversedBy: 'stages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FulfillmentType $type;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Order within the process. */
    #[ORM\Column]
    private int $position = 0;

    /** Terminal stage: the delivery is completed here. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isDone = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): FulfillmentType
    {
        return $this->type;
    }

    public function setType(FulfillmentType $type): static
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

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function setIsDone(bool $isDone): static
    {
        $this->isDone = $isDone;

        return $this;
    }
}
