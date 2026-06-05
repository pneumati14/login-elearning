<?php

namespace App\Entity;

use App\Repository\FeeTitleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A billing title for monthly fees (e.g. "Szoftver bérleti díj") — a
 * plain admin-managed master list, like the supplier list. Referenced
 * from the customer's billing data; deleting a title sets those
 * references null, so it is a hard delete with no cascade into
 * customer data.
 */
#[ORM\Entity(repositoryClass: FeeTitleRepository::class)]
#[ORM\Table(name: 'fee_title')]
class FeeTitle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

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
