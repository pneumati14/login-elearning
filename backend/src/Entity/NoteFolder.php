<?php

namespace App\Entity;

use App\Repository\NoteFolderRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A folder in a user's private notebook — the left-hand tree of the CRM
 * notes page. Folders may nest (a self-referencing parent) and belong to
 * exactly one owner; nobody else sees them. Deleting a folder cascades to
 * its sub-folders, but its [[Note]]s survive (their folder is set to null,
 * i.e. they fall back to the "uncategorised" bucket).
 */
#[ORM\Entity(repositoryClass: NoteFolderRepository::class)]
#[ORM\Table(name: 'note_folder')]
#[ORM\Index(name: 'idx_note_folder_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_note_folder_parent', columns: ['parent_id'])]
class NoteFolder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** Parent folder for nesting; null = a top-level folder. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?NoteFolder $parent = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column]
    private int $position = 0;

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getParent(): ?NoteFolder
    {
        return $this->parent;
    }

    public function setParent(?NoteFolder $parent): static
    {
        $this->parent = $parent;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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
