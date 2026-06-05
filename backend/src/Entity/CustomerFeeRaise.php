<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One percentage raise on the customer's WHOLE monthly fee, effective
 * from a date. Raises stack multiplicatively in date order on top of
 * the fee items' list prices — the items themselves are not rewritten,
 * the raise history lives in these rows. Removed with the customer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_fee_raise')]
#[ORM\Index(name: 'idx_customer_fee_raise_customer', columns: ['customer_id'])]
class CustomerFeeRaise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'feeRaises')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** Percentage, e.g. 8 = +8%; negative allowed for corrections. */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)]
    private string $percent = '0';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->effectiveFrom = new \DateTimeImmutable('today');
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

    public function getPercent(): string
    {
        return $this->percent;
    }

    public function setPercent(string $percent): static
    {
        $this->percent = $percent;

        return $this;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
