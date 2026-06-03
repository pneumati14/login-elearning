<?php

namespace App\Service;

use App\Entity\User;

/**
 * Turns a User entity into the plain array shape the API exposes.
 * The hashed password is deliberately never included.
 */
final class UserSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'roles' => $user->getRoles(),
            'role' => $user->getPrimaryRole(),
            'isAdmin' => \in_array(User::ROLE_ADMIN, $user->getRoles(), true),
            'avatarUrl' => null !== $user->getAvatarPath()
                ? '/api/users/'.$user->getId().'/avatar?v='.$user->getAvatarPath()
                : null,
            'locale' => $user->getLocale(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
