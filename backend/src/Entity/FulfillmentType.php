<?php

namespace App\Entity;

use App\Repository\FulfillmentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A fulfillment category (hardware / software / development project…)
 * for won deals, with its own ordered delivery stages. Admin-managed
 * master list, like the opportunity pipeline types.
 */
#[ORM\Entity(repositoryClass: FulfillmentTypeRepository::class)]
#[ORM\Table(name: 'fulfillment_type')]
class FulfillmentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Manual ordering in lists and on the board tabs. */
    #[ORM\Column]
    private int $position = 0;

    /**
     * Ordered delivery stages.
     *
     * @var Collection<int, FulfillmentStage>
     */
    #[ORM\OneToMany(mappedBy: 'type', targetEntity: FulfillmentStage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
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

    /**
     * @return Collection<int, FulfillmentStage>
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
