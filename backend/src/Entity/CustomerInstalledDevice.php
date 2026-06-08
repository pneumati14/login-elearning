<?php

namespace App\Entity;

use App\Repository\CustomerInstalledDeviceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A device installed at a customer site (access reader, terminal,
 * server, …). Sub-resource of [[Customer]]: deleted together with its
 * customer via onDelete CASCADE. The name may be prefilled from the
 * [[Product]] catalogue but is freely overridable; the optional product
 * link survives a catalogue deletion as SET NULL.
 */
#[ORM\Entity(repositoryClass: CustomerInstalledDeviceRepository::class)]
#[ORM\Table(name: 'customer_installed_device')]
#[ORM\Index(name: 'idx_installed_device_customer', columns: ['customer_id'])]
class CustomerInstalledDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'installedDevices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** Optional catalogue link; prefills the name, freely overridable. */
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $quantity = 1;

    /** When the device was installed (optional). */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $installedAt = null;

    /** Where it is installed, or any free-text note. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $location = null;

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

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);

        return $this;
    }

    public function getInstalledAt(): ?\DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function setInstalledAt(?\DateTimeImmutable $installedAt): static
    {
        $this->installedAt = $installedAt;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

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
