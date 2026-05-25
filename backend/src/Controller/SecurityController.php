<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SecurityController extends AbstractController
{
    /**
     * The json_login firewall intercepts this request — the method body
     * is never executed. The route only needs to exist as the check path.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Ezt a kérést a json_login tűzfal kezeli.');
    }

    /**
     * Likewise intercepted — by the logout firewall listener.
     */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Ezt a kérést a logout tűzfal kezeli.');
    }

    /**
     * Returns the currently authenticated user, used by the SPA on load
     * to restore the session.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user, UserSerializer $serializer): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Bejelentkezés szükséges.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->json($serializer->toArray($user));
    }

    /**
     * Lets the signed-in user change their own password. The current
     * password must be supplied and verified before the change applies.
     */
    #[Route('/api/me/password', name: 'api_me_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] ?User $user,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Bejelentkezés szükséges.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $current = (string) ($payload['currentPassword'] ?? '');
        $new = (string) ($payload['newPassword'] ?? '');

        if (!$passwordHasher->isPasswordValid($user, $current)) {
            return $this->json(
                ['error' => 'A jelenlegi jelszó helytelen.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if (\strlen($new) < 8) {
            return $this->json(
                ['error' => 'Az új jelszó legalább 8 karakter legyen.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if ($new === $current) {
            return $this->json(
                ['error' => 'Az új jelszó térjen el a jelenlegitől.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->setPassword($passwordHasher->hashPassword($user, $new));
        $entityManager->flush();

        return $this->json(['status' => 'password_changed']);
    }

    /**
     * Stores the signed-in user's preferred UI language on their account,
     * so it follows them across devices and sessions.
     */
    #[Route('/api/me/locale', name: 'api_me_locale', methods: ['POST'])]
    public function changeLocale(
        Request $request,
        #[CurrentUser] ?User $user,
        EntityManagerInterface $entityManager,
        UserSerializer $serializer,
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Bejelentkezés szükséges.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        $locale = \is_array($payload) ? (string) ($payload['locale'] ?? '') : '';

        if (!\in_array($locale, ['hu', 'en', 'az', 'de', 'pt', 'tr', 'pl', 'es'], true)) {
            return $this->json(['error' => 'Ismeretlen nyelv.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setLocale($locale);
        $entityManager->flush();

        return $this->json($serializer->toArray($user));
    }
}
