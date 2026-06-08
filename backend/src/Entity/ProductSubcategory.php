<?php

namespace App\Entity;

use App\Repository\ProductSubcategoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One ordered sub-category within a [[ProductCategory]] (e.g. Terminál,
 * Kártya olvasó under Hardver). Deleted together with its category via
 * onDelete CASCADE.
 */
#[ORM\Entity(repositoryClass: ProductSubcategoryRepository::class)]
#[ORM\Table(name: 'product_subcategory')]
#[ORM\Index(name: 'idx_product_subcategory_category', columns: ['category_id'])]
class ProductSubcategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductCategory::class, inversedBy: 'subcategories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductCategory $category;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Order within the category. */
    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ProductCategory
    {
        return $this->category;
    }

    public function setCategory(ProductCategory $category): static
    {
        $this->category = $category;

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
}
