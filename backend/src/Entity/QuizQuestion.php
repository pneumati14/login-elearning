<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_question')]
class QuizQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(name: 'quiz_id', nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\Column]
    private int $position = 0;

    /**
     * @var Collection<int, QuizOption>
     */
    #[ORM\OneToMany(targetEntity: QuizOption::class, mappedBy: 'question', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $options;

    public function __construct()
    {
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, QuizOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(QuizOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setQuestion($this);
        }

        return $this;
    }
}
