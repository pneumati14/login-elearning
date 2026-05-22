<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Létrehoz egy felhasználót — ezzel hozható létre az első admin.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail cím (ezzel lép be)')
            ->addArgument('password', InputArgument::REQUIRED, 'Jelszó (legalább 8 karakter)')
            ->addArgument('firstName', InputArgument::REQUIRED, 'Keresztnév')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Vezetéknév')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'A felhasználó admin jogot kap');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = trim((string) $input->getArgument('email'));
        $password = (string) $input->getArgument('password');
        $firstName = trim((string) $input->getArgument('firstName'));
        $lastName = trim((string) $input->getArgument('lastName'));
        $isAdmin = (bool) $input->getOption('admin');

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Érvénytelen e-mail cím.');

            return Command::FAILURE;
        }
        if (\strlen($password) < 8) {
            $io->error('A jelszó legalább 8 karakter legyen.');

            return Command::FAILURE;
        }
        if (null !== $this->users->findOneBy(['email' => $email])) {
            $io->error(sprintf('Már létezik felhasználó ezzel az e-mail címmel: %s', $email));

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s felhasználó létrehozva: %s',
            $isAdmin ? 'Admin' : 'Normál',
            $email,
        ));

        return Command::SUCCESS;
    }
}
