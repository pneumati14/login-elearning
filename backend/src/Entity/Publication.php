<?php

namespace App\Entity;

use App\Repository\PublicationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A research publication — bilingual title/description/topic/author and
 * an uploaded document, shown on the public Research page.
 */
#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'publication')]
class Publication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $title;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $description;

    /** Subject area, used for searching. */
    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $topic;

    /** Author(s) of the publication, used for searching. */
    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $author;

    /** Stored filename of the uploaded document. */
    #[ORM\Column(length: 255)]
    private string $filePath;

    /** The original upload filename, used as the download name. */
    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->title = new LocalizedText();
        $this->description = new LocalizedText();
        $this->topic = new LocalizedText();
        $this->author = new LocalizedText();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): LocalizedText
    {
        return $this->title;
    }

    public function getDescription(): LocalizedText
    {
        return $this->description;
    }

    public function getTopic(): LocalizedText
    {
        return $this->topic;
    }

    public function getAuthor(): LocalizedText
    {
        return $this->author;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
