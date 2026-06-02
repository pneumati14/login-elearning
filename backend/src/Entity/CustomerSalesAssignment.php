<?php

namespace App\Entity;

use App\Repository\CustomerSalesAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A salesperson assigned to a customer for a given period. A customer
 * may have multiple concurrent or sequential assignments — the period
 * (validFrom..validUntil) determines when each is active. Either end
 * may be null (open-ended).
 */
#[ORM\Entity(repositoryClass: CustomerSalesAssignmentRepository::class)]
#[ORM\Table(name: 'customer_sales_assignment')]
#[ORM\Index(name: 'idx_csa_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_csa_user', columns: ['user_id'])]
class CustomerSalesAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'salesAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Is this assignment active on the given date (defaults to today)? */
    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        $today = $date->setTime(0, 0, 0);
        if (null !== $this->validFrom && $this->validFrom > $today) return false;
        if (null !== $this->validUntil && $this->validUntil < $today) return false;

        return true;
    }
}
