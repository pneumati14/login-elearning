<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\Quiz;
use App\Entity\QuizOption;
use App\Entity\QuizQuestion;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Quiz authoring — administrators only. A quiz belongs to a course or a
 * lesson; its questions and options are replaced wholesale on save.
 */
#[Route('/api/admin', name: 'api_admin_quiz_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminQuizController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuizRepository $quizzes,
    ) {
    }

    #[Route('/courses/{id<\d+>}/quiz', name: 'course_quiz', methods: ['POST'])]
    public function courseQuiz(Course $course): JsonResponse
    {
        $quiz = $this->quizzes->findOneByCourse($course);
        if (null === $quiz) {
            $quiz = (new Quiz())->setCourse($course);
            $this->entityManager->persist($quiz);
            $this->entityManager->flush();
        }

        return $this->json($this->serialize($quiz));
    }

    #[Route('/lessons/{id<\d+>}/quiz', name: 'lesson_quiz', methods: ['POST'])]
    public function lessonQuiz(Lesson $lesson): JsonResponse
    {
        $quiz = $this->quizzes->findOneByLesson($lesson);
        if (null === $quiz) {
            $quiz = (new Quiz())->setLesson($lesson);
            $this->entityManager->persist($quiz);
            $this->entityManager->flush();
        }

        return $this->json($this->serialize($quiz));
    }

    #[Route('/quizzes/{id<\d+>}', name: 'get', methods: ['GET'])]
    public function get(Quiz $quiz): JsonResponse
    {
        return $this->json($this->serialize($quiz));
    }

    #[Route('/quizzes/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Quiz $quiz, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($payload['passThreshold']) && is_numeric($payload['passThreshold'])) {
            $quiz->setPassThreshold((int) $payload['passThreshold']);
        }

        // Replace the whole question set: drop the old ones, rebuild from
        // the payload. orphanRemoval + DB cascade clean up the options.
        $quiz->getQuestions()->clear();
        $this->entityManager->flush();

        $questions = \is_array($payload['questions'] ?? null) ? $payload['questions'] : [];
        $questionPos = 1;
        foreach ($questions as $questionData) {
            if (!\is_array($questionData)) {
                continue;
            }
            $text = trim((string) ($questionData['text'] ?? ''));
            if ('' === $text) {
                continue;
            }

            $question = (new QuizQuestion())->setText($text)->setPosition($questionPos++);
            $quiz->addQuestion($question);
            $this->entityManager->persist($question);

            $optionPos = 1;
            $options = \is_array($questionData['options'] ?? null) ? $questionData['options'] : [];
            foreach ($options as $optionData) {
                if (!\is_array($optionData)) {
                    continue;
                }
                $optionText = trim((string) ($optionData['text'] ?? ''));
                if ('' === $optionText) {
                    continue;
                }

                $option = (new QuizOption())
                    ->setText($optionText)
                    ->setCorrect((bool) ($optionData['correct'] ?? false))
                    ->setPosition($optionPos++);
                $question->addOption($option);
                $this->entityManager->persist($option);
            }
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($quiz));
    }

    #[Route('/quizzes/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Quiz $quiz): JsonResponse
    {
        $this->entityManager->remove($quiz);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Full quiz serialization, including the `correct` flags — for admins.
     *
     * @return array<string, mixed>
     */
    private function serialize(Quiz $quiz): array
    {
        return [
            'id' => $quiz->getId(),
            'passThreshold' => $quiz->getPassThreshold(),
            'questions' => array_map(static fn (QuizQuestion $question): array => [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'position' => $question->getPosition(),
                'options' => array_map(static fn (QuizOption $option): array => [
                    'id' => $option->getId(),
                    'text' => $option->getText(),
                    'correct' => $option->isCorrect(),
                    'position' => $option->getPosition(),
                ], $question->getOptions()->toArray()),
            ], $quiz->getQuestions()->toArray()),
        ];
    }
}
