<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A private note in the CRM notes page. Belongs to one owner (nobody else
 * sees it) and optionally lives in a [[NoteFolder]]. The note is a
 * free-standing draft: a button "sends" a copy of it to a customer as an
 * [[Activity]] (type=note), recorded as a [[NoteSubmission]] — the note
 * itself stays put and remains editable.
 */
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'note')]
#[ORM\Index(name: 'idx_note_owner', columns: ['owner_id'])]
#[ORM\Index(name: 'idx_note_folder', columns: ['folder_id'])]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** Folder this note lives in; null = uncategorised. */
    #[ORM\ManyToOne(targetEntity: NoteFolder::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?NoteFolder $folder = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    /** @var Collection<int, NoteSubmission> */
    #[ORM\OneToMany(mappedBy: 'note', targetEntity: NoteSubmission::class, cascade: ['remove'])]
    #[ORM\OrderBy(['sentAt' => 'DESC'])]
    private Collection $submissions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->submissions = new ArrayCollection();
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

    public function getFolder(): ?NoteFolder
    {
        return $this->folder;
    }

    public function setFolder(?NoteFolder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /** @return Collection<int, NoteSubmission> */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
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
