<?php

namespace App\Entity;

use App\Repository\CustomerArchitectureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * The customer's system-architecture sheet (the "Architektúra" tab):
 * deployment model (on-prem / SaaS), the hosting server for SaaS, VPN
 * and user notes for on-prem, free-text notes and the linked
 * [[Integration]] catalogue entries. One row per customer, created
 * lazily on first save; removed with the customer (onDelete CASCADE).
 * The attachments live separately in [[CustomerArchitectureFile]].
 */
#[ORM\Entity(repositoryClass: CustomerArchitectureRepository::class)]
#[ORM\Table(name: 'customer_architecture')]
class CustomerArchitecture
{
    public const MODEL_ONPREM = 'onprem';
    public const MODEL_SAAS = 'saas';
    public const MODELS = [self::MODEL_ONPREM, self::MODEL_SAAS];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Customer $customer;

    /** One of {@see MODELS}, or null while undecided. */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $deploymentModel = null;

    /** For SaaS: which of our servers hosts the customer. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $saasServer = null;

    /**
     * For on-prem: how to reach the system (VPN endpoint, account name…).
     * Do NOT store passwords here — reference the password manager.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $vpnInfo = null;

    /** Who uses the system on the customer's side. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $usersInfo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * The external systems this customer integrates with.
     *
     * @var Collection<int, Integration>
     */
    #[ORM\ManyToMany(targetEntity: Integration::class)]
    #[ORM\JoinTable(name: 'customer_architecture_integration')]
    private Collection $integrations;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->integrations = new ArrayCollection();
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

    public function getDeploymentModel(): ?string
    {
        return $this->deploymentModel;
    }

    public function setDeploymentModel(?string $deploymentModel): static
    {
        $this->deploymentModel = \in_array($deploymentModel, self::MODELS, true) ? $deploymentModel : null;

        return $this;
    }

    public function getSaasServer(): ?string
    {
        return $this->saasServer;
    }

    public function setSaasServer(?string $saasServer): static
    {
        $this->saasServer = $saasServer;

        return $this;
    }

    public function getVpnInfo(): ?string
    {
        return $this->vpnInfo;
    }

    public function setVpnInfo(?string $vpnInfo): static
    {
        $this->vpnInfo = $vpnInfo;

        return $this;
    }

    public function getUsersInfo(): ?string
    {
        return $this->usersInfo;
    }

    public function setUsersInfo(?string $usersInfo): static
    {
        $this->usersInfo = $usersInfo;

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

    /**
     * @return Collection<int, Integration>
     */
    public function getIntegrations(): Collection
    {
        return $this->integrations;
    }

    public function clearIntegrations(): void
    {
        $this->integrations->clear();
    }

    public function addIntegration(Integration $integration): static
    {
        if (!$this->integrations->contains($integration)) {
            $this->integrations->add($integration);
        }

        return $this;
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
