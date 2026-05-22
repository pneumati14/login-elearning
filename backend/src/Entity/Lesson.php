<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\Table(name: 'lesson')]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $title;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $content;

    /**
     * Stored filename of an uploaded video, relative to the lesson
     * media directory. Null when no video has been uploaded.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoPath = null;

    /**
     * Stored filename of an uploaded PDF, relative to the lesson media
     * directory.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    /**
     * An optional YouTube video URL embedded in the lesson.
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $youtubeUrl = null;

    /**
     * Stored filename of an uploaded cover image, relative to the media
     * directory.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPath = null;

    /**
     * Ordering of the lesson within its course.
     */
    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(name: 'course_id', nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->title = new LocalizedText();
        $this->content = new LocalizedText();
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

    public function getContent(): LocalizedText
    {
        return $this->content;
    }

    public function getVideoPath(): ?string
    {
        return $this->videoPath;
    }

    public function setVideoPath(?string $videoPath): static
    {
        $this->videoPath = $videoPath;

        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;

        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;

        return $this;
    }

    public function getCoverPath(): ?string
    {
        return $this->coverPath;
    }

    public function setCoverPath(?string $coverPath): static
    {
        $this->coverPath = $coverPath;

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

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
