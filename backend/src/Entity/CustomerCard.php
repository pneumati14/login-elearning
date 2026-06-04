<?php

namespace App\Entity;

use App\Repository\CustomerCardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A card (access/ID card type) used by a [[Customer]]: free-text type
 * and uniqueness description, an optional [[Supplier]], and the orders
 * placed for it over time. Removed together with the customer.
 */
#[ORM\Entity(repositoryClass: CustomerCardRepository::class)]
#[ORM\Table(name: 'customer_card')]
#[ORM\Index(name: 'idx_customer_card_customer', columns: ['customer_id'])]
class CustomerCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** Card type, free text (e.g. Mifare 1K, EM4200, mágnescsíkos). */
    #[ORM\Column(length: 255)]
    private string $type = '';

    /** Free-text uniqueness/personalisation description. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $uniqueness = null;

    /** Supplier of this card; reference only (SET NULL on delete). */
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Supplier $supplier = null;

    /**
     * Orders placed for this card, newest first.
     *
     * @var Collection<int, CustomerCardOrder>
     */
    #[ORM\OneToMany(mappedBy: 'card', targetEntity: CustomerCardOrder::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $orders;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->orders = new ArrayCollection();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUniqueness(): ?string
    {
        return $this->uniqueness;
    }

    public function setUniqueness(?string $uniqueness): static
    {
        $this->uniqueness = $uniqueness;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    /**
     * @return Collection<int, CustomerCardOrder>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(CustomerCardOrder $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setCard($this);
        }

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
