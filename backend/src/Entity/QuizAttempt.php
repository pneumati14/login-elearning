<?php

namespace App\Entity;

use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One recorded attempt at a quiz by a user, with the resulting score.
 */
#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
#[ORM\Table(name: 'quiz_attempt')]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(name: 'quiz_id', nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[ORM\Column]
    private int $score;

    #[ORM\Column]
    private int $total;

    #[ORM\Column]
    private bool $passed;

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function __construct(User $user, Quiz $quiz, int $score, int $total, bool $passed)
    {
        $this->user = $user;
        $this->quiz = $quiz;
        $this->score = $score;
        $this->total = $total;
        $this->passed = $passed;
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

    public function getQuiz(): Quiz
    {
        return $this->quiz;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
