<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AvatarStorage;
use App\Service\UserSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Profile-picture upload, removal and serving. Users manage their own
 * avatar; any signed-in user may view an avatar.
 */
final class AvatarController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AvatarStorage $storage,
        private readonly UserSerializer $serializer,
    ) {
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar_upload', methods: ['POST'])]
    public function upload(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Bejelentkezés szükséges.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->json(['error' => 'A feltöltött fájl nem kép.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->storage->delete($user->getAvatarPath());
        $user->setAvatarPath($this->storage->store($file));
        $this->entityManager->flush();

        return $this->json($this->serializer->toArray($user));
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json(['error' => 'Bejelentkezés szükséges.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $this->storage->delete($user->getAvatarPath());
        $user->setAvatarPath(null);
        $this->entityManager->flush();

        return $this->json($this->serializer->toArray($user));
    }

    #[Route('/api/users/{id<\d+>}/avatar', name: 'api_user_avatar', methods: ['GET'])]
    public function serve(User $user): BinaryFileResponse
    {
        $name = $user->getAvatarPath();
        if (null === $name || !is_file($this->storage->path($name))) {
            throw $this->createNotFoundException('Ehhez a felhasználóhoz nincs profilkép.');
        }

        return new BinaryFileResponse($this->storage->path($name));
    }
}
