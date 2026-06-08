<?php

namespace App\Entity;

use App\Repository\ProductCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A top-level product category (e.g. Hardver / Szoftver) with its own
 * ordered sub-categories. Admin-managed master list, like the
 * fulfillment categories. Products reference a category (required in the
 * form) and optionally one of its sub-categories.
 */
#[ORM\Entity(repositoryClass: ProductCategoryRepository::class)]
#[ORM\Table(name: 'product_category')]
class ProductCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Manual ordering in lists and selects. */
    #[ORM\Column]
    private int $position = 0;

    /**
     * Ordered sub-categories.
     *
     * @var Collection<int, ProductSubcategory>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ProductSubcategory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $subcategories;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->subcategories = new ArrayCollection();
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
     * @return Collection<int, ProductSubcategory>
     */
    public function getSubcategories(): Collection
    {
        return $this->subcategories;
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
