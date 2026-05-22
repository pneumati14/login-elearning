<?php

namespace App\Entity;

use App\Repository\QuizRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A quiz belongs to exactly one owner — either a course or a lesson.
 * The nullable, unique course_id / lesson_id columns enforce at most
 * one quiz per course and per lesson.
 */
#[ORM\Entity(repositoryClass: QuizRepository::class)]
#[ORM\Table(name: 'quiz')]
#[ORM\UniqueConstraint(name: 'uniq_quiz_course', columns: ['course_id'])]
#[ORM\UniqueConstraint(name: 'uniq_quiz_lesson', columns: ['lesson_id'])]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', nullable: true, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(name: 'lesson_id', nullable: true, onDelete: 'CASCADE')]
    private ?Lesson $lesson = null;

    /** Minimum percentage of correct answers required to pass. */
    #[ORM\Column]
    private int $passThreshold = 60;

    /**
     * @var Collection<int, QuizQuestion>
     */
    #[ORM\OneToMany(targetEntity: QuizQuestion::class, mappedBy: 'quiz', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): static
    {
        $this->lesson = $lesson;

        return $this;
    }

    public function getPassThreshold(): int
    {
        return $this->passThreshold;
    }

    public function setPassThreshold(int $passThreshold): static
    {
        $this->passThreshold = max(1, min(100, $passThreshold));

        return $this;
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(QuizQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
