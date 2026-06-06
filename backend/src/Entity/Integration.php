<?php

namespace App\Entity;

use App\Repository\IntegrationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One integratable external system (e.g. "SAP", "Nexon payroll") — a
 * plain admin-managed master list like the fee titles. Each entry sits
 * in one fixed category (payroll / ERP / access control / other); the
 * customer's architecture tab picks any number of them. Hard delete —
 * the customer links simply disappear with the row.
 */
#[ORM\Entity(repositoryClass: IntegrationRepository::class)]
#[ORM\Table(name: 'integration')]
class Integration
{
    public const CATEGORY_PAYROLL = 'payroll';
    public const CATEGORY_ERP = 'erp';
    public const CATEGORY_ACCESS_CONTROL = 'access_control';
    public const CATEGORY_OTHER = 'other';
    public const CATEGORIES = [
        self::CATEGORY_PAYROLL,
        self::CATEGORY_ERP,
        self::CATEGORY_ACCESS_CONTROL,
        self::CATEGORY_OTHER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** One of {@see CATEGORIES}. */
    #[ORM\Column(length: 32)]
    private string $category = self::CATEGORY_OTHER;

    /** Inactive entries stay on customers but cannot be newly picked. */
    #[ORM\Column]
    private bool $isActive = true;

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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = \in_array($category, self::CATEGORIES, true) ? $category : self::CATEGORY_OTHER;

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
