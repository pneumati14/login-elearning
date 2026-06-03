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
 * User management. Creating, deleting, resetting passwords and changing
 * roles is administrators only. Reading the list is also open to sales
 * managers — they need it to pick a salesperson when assigning customers.
 * There is no public registration; admins create every account.
 */
#[Route('/api/admin/users', name: 'api_admin_users_')]
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
    #[IsGranted('ROLE_SALES_MANAGER')]
    public function list(): JsonResponse
    {
        $data = array_map(
            $this->serializer->toArray(...),
            $this->users->findBy([], ['createdAt' => 'DESC']),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
        $roles = User::rolesForPrimary((string) ($payload['role'] ?? 'user'));

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
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json($this->serializer->toArray($user), JsonResponse::HTTP_CREATED);
    }

    /**
     * Change a user's role. Body: { "role": "user|sales|sales_manager|admin" }.
     * An admin cannot change their own role — that's a footgun (self-lockout),
     * so we refuse it and keep at least the acting admin an admin.
     */
    #[Route('/{id<\d+>}/role', name: 'set_role', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function setRole(User $user, Request $request, #[CurrentUser] ?User $current): JsonResponse
    {
        if (null !== $current && $user->getId() === $current->getId()) {
            return $this->json(
                ['error' => 'A saját szerepkörödet nem módosíthatod.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $token = (string) ($payload['role'] ?? '');
        if (!\in_array($token, ['user', 'sales', 'sales_manager', 'admin'], true)) {
            return $this->json(['error' => 'Ismeretlen szerepkör.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setRoles(User::rolesForPrimary($token));
        $this->entityManager->flush();

        return $this->json($this->serializer->toArray($user));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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
    #[IsGranted('ROLE_ADMIN')]
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
