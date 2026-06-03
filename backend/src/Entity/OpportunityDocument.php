<?php

namespace App\Entity;

use App\Repository\OpportunityDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An uploaded document (the offer/quote PDF) attached to an
 * [[Opportunity]]. Multiple documents per opportunity are allowed. The
 * file lives on disk (see MediaStorage, "opportunities" sub-directory);
 * this entity keeps the stored name plus the original filename for
 * download. Removed together with its opportunity (onDelete CASCADE).
 */
#[ORM\Entity(repositoryClass: OpportunityDocumentRepository::class)]
#[ORM\Table(name: 'opportunity_document')]
#[ORM\Index(name: 'idx_opp_document_opportunity', columns: ['opportunity_id'])]
class OpportunityDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Opportunity $opportunity;

    /** Random name on disk. */
    #[ORM\Column(length: 255)]
    private string $storedName = '';

    /** Original filename, shown and used as the download name. */
    #[ORM\Column(length: 255)]
    private string $originalName = '';

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    /** The admin who uploaded it; null if that user was later removed. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpportunity(): Opportunity
    {
        return $this->opportunity;
    }

    public function setOpportunity(Opportunity $opportunity): static
    {
        $this->opportunity = $opportunity;

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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
