<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AvatarStorage;
use App\Service\UserSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User management — only administrators may reach these endpoints.
 * There is no public registration; admins create every account.
 */
#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserSerializer $serializer,
        private readonly AvatarStorage $avatars,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            $this->serializer->toArray(...),
            $this->users->findBy([], ['createdAt' => 'DESC']),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $isAdmin = ($payload['role'] ?? 'user') === 'admin';

        $errors = [];
        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adj meg egy érvényes e-mail címet.';
        }
        if ('' === $firstName) {
            $errors[] = 'A keresztnév kötelező.';
        }
        if ('' === $lastName) {
            $errors[] = 'A vezetéknév kötelező.';
        }
        if (\strlen($password) < 8) {
            $errors[] = 'A jelszó legalább 8 karakter legyen.';
        }
        if ('' !== $email && null !== $this->users->findOneBy(['email' => $email])) {
            $errors[] = 'Ezzel az e-mail címmel már létezik felhasználó.';
        }

        if ([] !== $errors) {
            return $this->json(['error' => implode(' ', $errors)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json($this->serializer->toArray($user), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(User $user, #[CurrentUser] ?User $current): JsonResponse
    {
        if (null !== $current && $user->getId() === $current->getId()) {
            return $this->json(
                ['error' => 'A saját fiókodat nem törölheted.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->avatars->delete($user->getAvatarPath());
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Resets a user's password. No current password is required — this is
     * an administrative override, available only to administrators.
     */
    #[Route('/{id<\d+>}/password', name: 'set_password', methods: ['PUT'])]
    public function setPassword(User $user, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $new = (string) ($payload['newPassword'] ?? '');
        if (\strlen($new) < 8) {
            return $this->json(
                ['error' => 'Az új jelszó legalább 8 karakter legyen.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $new));
        $this->entityManager->flush();

        return $this->json(['status' => 'password_changed']);
    }
}
