<?php

namespace App\Entity;

use App\Repository\CustomerArchitectureFileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One attachment on the customer's architecture tab, typed by what it
 * documents: architecture diagram, system plan, SDD (Solution Design
 * Document) or other. PDF, Word or image; the file itself lives in
 * MediaStorage's "architecture" folder under a random stored name.
 * Removed together with the customer.
 */
#[ORM\Entity(repositoryClass: CustomerArchitectureFileRepository::class)]
#[ORM\Table(name: 'customer_architecture_file')]
#[ORM\Index(name: 'idx_customer_architecture_file_customer', columns: ['customer_id'])]
class CustomerArchitectureFile
{
    public const KIND_DIAGRAM = 'diagram';
    public const KIND_PLAN = 'plan';
    public const KIND_SDD = 'sdd';
    public const KIND_OTHER = 'other';
    public const KINDS = [self::KIND_DIAGRAM, self::KIND_PLAN, self::KIND_SDD, self::KIND_OTHER];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** One of {@see KINDS}. */
    #[ORM\Column(length: 16)]
    private string $kind = self::KIND_OTHER;

    /** Random stored name inside the architecture folder. */
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

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): static
    {
        $this->kind = \in_array($kind, self::KINDS, true) ? $kind : self::KIND_OTHER;

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
