<?php

namespace App\Entity;

use App\Repository\LessonCompletionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records that a user has finished a lesson. One row per (user, lesson) pair.
 */
#[ORM\Entity(repositoryClass: LessonCompletionRepository::class)]
#[ORM\Table(name: 'lesson_completion')]
#[ORM\UniqueConstraint(name: 'uniq_completion_user_lesson', columns: ['user_id', 'lesson_id'])]
class LessonCompletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(name: 'lesson_id', nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function __construct(User $user, Lesson $lesson)
    {
        $this->user = $user;
        $this->lesson = $lesson;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
