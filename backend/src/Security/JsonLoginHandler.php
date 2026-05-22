<?php

namespace App\Security;

use App\Entity\User;
use App\Service\UserSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Translates the outcome of the json_login firewall into JSON:
 * the authenticated user on success, an error message on failure.
 */
final class JsonLoginHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    public function __construct(private readonly UserSerializer $userSerializer)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Ismeretlen felhasználó.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($this->userSerializer->toArray($user));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        // Brute-force throttling kicked in — tell the client to back off
        // with a 429 instead of masking it as a normal credential error.
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse(
                ['error' => 'Túl sok sikertelen bejelentkezési kísérlet. Próbáld újra később.'],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
            );
        }

        return new JsonResponse(
            ['error' => 'Hibás e-mail cím vagy jelszó.'],
            JsonResponse::HTTP_UNAUTHORIZED,
        );
    }
}
