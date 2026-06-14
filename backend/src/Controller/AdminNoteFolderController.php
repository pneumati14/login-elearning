<?php

namespace App\Controller;

use App\Entity\NoteFolder;
use App\Entity\User;
use App\Repository\NoteFolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Private notebook folders for the CRM notes page — the left-hand tree.
 * Every folder belongs to the current user; the controller only ever
 * reads or writes the caller's own folders (owner scoping on each lookup).
 */
#[Route('/api/admin/note-folders', name: 'api_admin_note_folders_')]
#[IsGranted('ROLE_SALES')]
final class AdminNoteFolderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteFolderRepository $folders,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $owner = $this->currentUser();
        if (null === $owner) {
            return $this->json([]);
        }

        $data = array_map(
            fn (NoteFolder $f): array => $this->serialize($f),
            $this->folders->findForOwner($owner),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $owner = $this->currentUser();
        if (null === $owner) {
            return $this->json(['error' => 'Nincs bejelentkezett felhasználó.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $folder = new NoteFolder();
        $folder->setOwner($owner);

        $error = $this->apply($folder, $owner, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return $this->json($this->serialize($folder), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $folder = $this->findOwnFolder($id);
        if (null === $folder) {
            return $this->json(['error' => 'A mappa nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($folder, $folder->getOwner(), $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $folder->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($folder));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $folder = $this->findOwnFolder($id);
        if (null === $folder) {
            return $this->json(['error' => 'A mappa nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Sub-folders cascade; the notes inside fall back to uncategorised
        // (folder set null on delete).
        $this->entityManager->remove($folder);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(NoteFolder $folder, User $owner, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'A mappa neve kötelező.';
        }
        $folder->setName($name);

        if (\array_key_exists('position', $payload) && is_numeric($payload['position'])) {
            $folder->setPosition((int) $payload['position']);
        }

        if (\array_key_exists('parentId', $payload)) {
            $parentId = $payload['parentId'];
            if (null === $parentId || '' === $parentId) {
                $folder->setParent(null);
            } else {
                $parent = $this->findOwnFolder((int) $parentId, $owner);
                if (null === $parent) {
                    return 'A szülőmappa nem található.';
                }
                // Guard against a folder becoming its own ancestor.
                if (null !== $folder->getId() && $this->isDescendantOrSelf($parent, $folder)) {
                    return 'A mappa nem helyezhető önmaga alá.';
                }
                $folder->setParent($parent);
            }
        }

        return null;
    }

    /** True if $candidate is $folder or sits anywhere below it. */
    private function isDescendantOrSelf(NoteFolder $candidate, NoteFolder $folder): bool
    {
        $cursor = $candidate;
        // Bounded walk up the parent chain — guards against cycles too.
        for ($i = 0; $i < 100 && null !== $cursor; ++$i) {
            if ($cursor->getId() === $folder->getId()) {
                return true;
            }
            $cursor = $cursor->getParent();
        }

        return false;
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function findOwnFolder(int $id, ?User $owner = null): ?NoteFolder
    {
        $owner ??= $this->currentUser();
        if (null === $owner) {
            return null;
        }
        $folder = $this->entityManager->find(NoteFolder::class, $id);
        if (!$folder instanceof NoteFolder || $folder->getOwner()?->getId() !== $owner->getId()) {
            return null;
        }

        return $folder;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(NoteFolder $f): array
    {
        return [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'parentId' => $f->getParent()?->getId(),
            'position' => $f->getPosition(),
            'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $f->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }
}
