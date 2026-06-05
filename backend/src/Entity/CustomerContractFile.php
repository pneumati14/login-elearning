<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One contract attachment on the customer's billing tab (PDF, Word or
 * image). The file itself lives in MediaStorage's "contracts" folder
 * under a random stored name; the original name and MIME type are kept
 * for download. Removed together with the customer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_contract_file')]
#[ORM\Index(name: 'idx_customer_contract_file_customer', columns: ['customer_id'])]
class CustomerContractFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'contractFiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** Random stored name inside the contracts folder. */
    #[ORM\Column(length: 64)]
    private string $storedName = '';

    /** The uploaded file's original name, shown in the list. */
    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(length: 100)]
    private string $mimeType = '';

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

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): static
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
