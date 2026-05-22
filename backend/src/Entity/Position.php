<?php

namespace App\Entity;

use App\Repository\PositionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An open job position shown on the public Team & Career page and
 * managed by administrators. Text fields are bilingual ([[LocalizedText]]).
 */
#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'job_position')]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $title;

    /** Where the role is based, e.g. "Budapest · Hybrid". */
    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $location;

    /** Employment type, e.g. "Full-time". */
    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $type;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $summary;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->title = new LocalizedText();
        $this->location = new LocalizedText();
        $this->type = new LocalizedText();
        $this->summary = new LocalizedText();
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

    public function getLocation(): LocalizedText
    {
        return $this->location;
    }

    public function getType(): LocalizedText
    {
        return $this->type;
    }

    public function getSummary(): LocalizedText
    {
        return $this->summary;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
