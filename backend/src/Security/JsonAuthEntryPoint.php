<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Entry point for the API firewall: an unauthenticated request to a
 * protected route gets a clean JSON 401 rather than an HTML redirect.
 */
final class JsonAuthEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Bejelentkezés szükséges.'],
            JsonResponse::HTTP_UNAUTHORIZED,
        );
    }
}
