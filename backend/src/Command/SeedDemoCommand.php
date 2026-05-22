<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Loads a few demo courses and lessons into the database.',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseRepository $courseRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->courseRepository->count([]) > 0) {
            $io->warning('The database already contains courses — nothing to seed.');

            return Command::SUCCESS;
        }

        /** @var array<array{string, string, string, list<string>}> $demoCourses */
        $demoCourses = [
            [
                'Bevezetés a webfejlesztésbe',
                'bevezetes-a-webfejlesztesbe',
                'A HTML, CSS és JavaScript alapjai kezdőknek, gyakorlati példákkal.',
                ['Mi az a HTML?', 'CSS és a megjelenés', 'JavaScript első lépések'],
            ],
            [
                'PHP és Symfony alapok',
                'php-es-symfony-alapok',
                'Backend fejlesztés a Symfony keretrendszerrel, a nulláról.',
                ['A PHP nyelv alapjai', 'Symfony telepítése', 'Az első controller'],
            ],
            [
                'Vue.js a gyakorlatban',
                'vuejs-a-gyakorlatban',
                'Modern, egyoldalas frontend alkalmazások építése Vue 3-mal.',
                ['Komponensek és reaktivitás', 'Útvonalkezelés Vue Routerrel', 'Állapotkezelés Piniával'],
            ],
        ];

        foreach ($demoCourses as [$title, $slug, $description, $lessonTitles]) {
            $course = new Course();
            $course->setSlug($slug);
            $course->getTitle()->setEn($title);
            $course->getDescription()->setEn($description);

            foreach ($lessonTitles as $index => $lessonTitle) {
                $lesson = new Lesson();
                $lesson->setPosition($index + 1);
                $lesson->getTitle()->setEn($lessonTitle);
                $lesson->getContent()->setEn('A(z) „'.$lessonTitle.'” lecke tartalma hamarosan elkészül.');

                $course->addLesson($lesson);
            }

            $this->entityManager->persist($course);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d demo kurzus betöltve.', count($demoCourses)));

        return Command::SUCCESS;
    }
}
