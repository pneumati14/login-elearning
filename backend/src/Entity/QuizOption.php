<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_option')]
class QuizOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuizQuestion::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'question_id', nullable: false, onDelete: 'CASCADE')]
    private ?QuizQuestion $question = null;

    #[ORM\Column(length: 500)]
    private string $text;

    #[ORM\Column]
    private bool $correct = false;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?QuizQuestion
    {
        return $this->question;
    }

    public function setQuestion(?QuizQuestion $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function setCorrect(bool $correct): static
    {
        $this->correct = $correct;

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
}
