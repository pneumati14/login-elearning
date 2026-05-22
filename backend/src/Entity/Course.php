<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'course')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $title;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $description;

    /**
     * Stored filename of an uploaded cover image, relative to the media
     * directory. Null when no cover has been uploaded.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverPath = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'instructor_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $instructor = null;

    /**
     * @var Collection<int, Lesson>
     */
    #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'course', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lessons;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->title = new LocalizedText();
        $this->description = new LocalizedText();
        $this->lessons = new ArrayCollection();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): LocalizedText
    {
        return $this->description;
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

    public function getInstructor(): ?User
    {
        return $this->instructor;
    }

    public function setInstructor(?User $instructor): static
    {
        $this->instructor = $instructor;

        return $this;
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function getLessons(): Collection
    {
        return $this->lessons;
    }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons->add($lesson);
            $lesson->setCourse($this);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): static
    {
        // orphanRemoval deletes the lesson once it is detached from the course.
        $this->lessons->removeElement($lesson);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
