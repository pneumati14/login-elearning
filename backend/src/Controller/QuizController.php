<?php

namespace App\Controller;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\QuizOption;
use App\Entity\QuizQuestion;
use App\Entity\User;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Quiz taking for authenticated users. Correct answers are never sent
 * to the client; scoring happens server-side.
 */
final class QuizController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuizAttemptRepository $attempts,
    ) {
    }

    #[Route('/api/quizzes/{id<\d+>}', name: 'api_quiz_show', methods: ['GET'])]
    public function show(Quiz $quiz, #[CurrentUser] User $user): JsonResponse
    {
        $last = $this->attempts->findLatestForUserAndQuiz($user, $quiz);

        return $this->json([
            'id' => $quiz->getId(),
            'title' => $this->ownerTitle($quiz),
            'passThreshold' => $quiz->getPassThreshold(),
            'questions' => array_map(static fn (QuizQuestion $question): array => [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'options' => array_map(static fn (QuizOption $option): array => [
                    'id' => $option->getId(),
                    'text' => $option->getText(),
                ], $question->getOptions()->toArray()),
            ], $quiz->getQuestions()->toArray()),
            'lastAttempt' => null !== $last ? [
                'score' => $last->getScore(),
                'total' => $last->getTotal(),
                'passed' => $last->isPassed(),
            ] : null,
        ]);
    }

    #[Route('/api/quizzes/{id<\d+>}/attempt', name: 'api_quiz_attempt', methods: ['POST'])]
    public function attempt(Quiz $quiz, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        /** @var array<int|string, mixed> $answers */
        $answers = \is_array($payload) && \is_array($payload['answers'] ?? null) ? $payload['answers'] : [];

        $questions = $quiz->getQuestions();
        $total = $questions->count();
        if (0 === $total) {
            return $this->json(['error' => 'Ehhez a quizhez még nincs kérdés.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $score = 0;
        foreach ($questions as $question) {
            $chosen = $answers[$question->getId()] ?? null;
            if (null === $chosen) {
                continue;
            }
            foreach ($question->getOptions() as $option) {
                if ($option->getId() === (int) $chosen && $option->isCorrect()) {
                    ++$score;
                    break;
                }
            }
        }

        $percent = (int) round($score / $total * 100);
        $passed = $percent >= $quiz->getPassThreshold();

        $this->entityManager->persist(new QuizAttempt($user, $quiz, $score, $total, $passed));
        $this->entityManager->flush();

        return $this->json([
            'score' => $score,
            'total' => $total,
            'percent' => $percent,
            'passed' => $passed,
        ]);
    }

    /**
     * @return array{en: string, hu: string|null}
     */
    private function ownerTitle(Quiz $quiz): array
    {
        if (null !== $quiz->getCourse()) {
            return $quiz->getCourse()->getTitle()->toArray();
        }
        if (null !== $quiz->getLesson()) {
            return $quiz->getLesson()->getTitle()->toArray();
        }

        return ['en' => 'Quiz', 'hu' => null];
    }
}
