<?php

namespace App\Controller;

use App\Entity\LocalizedText;
use App\Entity\Publication;
use App\Service\PublicationStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Research publication management — administrators only. Text fields are
 * bilingual: the multipart request carries `<field>En` / `<field>Hu`
 * pairs, English required.
 */
#[Route('/api/admin/publications', name: 'api_admin_publications_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminPublicationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicationStorage $storage,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        $publication = new Publication();
        $this->applyLocalized($publication->getTitle(), $request, 'title');
        $this->applyLocalized($publication->getDescription(), $request, 'description');
        $this->applyLocalized($publication->getTopic(), $request, 'topic');
        $this->applyLocalized($publication->getAuthor(), $request, 'author');

        if ('' === $publication->getTitle()->getEn()) {
            return $this->json(['error' => 'A publikáció angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if ('application/pdf' !== $file->getMimeType()) {
            return $this->json(['error' => 'A feltöltött fájl nem PDF.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $publication
            ->setOriginalName($file->getClientOriginalName())
            ->setFilePath($this->storage->store($file));

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        return $this->json($this->serialize($publication), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['POST'])]
    public function update(Publication $publication, Request $request): JsonResponse
    {
        $this->applyLocalized($publication->getTitle(), $request, 'title');
        $this->applyLocalized($publication->getDescription(), $request, 'description');
        $this->applyLocalized($publication->getTopic(), $request, 'topic');
        $this->applyLocalized($publication->getAuthor(), $request, 'author');

        if ('' === $publication->getTitle()->getEn()) {
            return $this->json(['error' => 'A publikáció angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The PDF is only replaced when a new one is uploaded.
        $file = $request->files->get('file');
        if ($file instanceof UploadedFile) {
            if ('application/pdf' !== $file->getMimeType()) {
                return $this->json(['error' => 'A feltöltött fájl nem PDF.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->storage->delete($publication->getFilePath());
            $publication
                ->setOriginalName($file->getClientOriginalName())
                ->setFilePath($this->storage->store($file));
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($publication));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Publication $publication): JsonResponse
    {
        $this->storage->delete($publication->getFilePath());
        $this->entityManager->remove($publication);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Reads `<key>En` / `<key>Hu` / `<key>Az` / `<key>De` / `<key>Pt` / `<key>Tr` / `<key>Pl` / `<key>Es` form fields into a LocalizedText.
     */
    private function applyLocalized(LocalizedText $field, Request $request, string $key): void
    {
        $en = trim((string) $request->request->get($key.'En', ''));
        $hu = trim((string) $request->request->get($key.'Hu', ''));
        $az = trim((string) $request->request->get($key.'Az', ''));
        $de = trim((string) $request->request->get($key.'De', ''));
        $pt = trim((string) $request->request->get($key.'Pt', ''));
        $tr = trim((string) $request->request->get($key.'Tr', ''));
        $pl = trim((string) $request->request->get($key.'Pl', ''));
        $es = trim((string) $request->request->get($key.'Es', ''));
        $field->setEn($en)->setHu($hu)->setAz($az)->setDe($de)->setPt($pt)->setTr($tr)->setPl($pl)->setEs($es);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Publication $publication): array
    {
        return [
            'id' => $publication->getId(),
            'title' => $publication->getTitle()->toArray(),
            'description' => $publication->getDescription()->toArray(),
            'topic' => $publication->getTopic()->toArray(),
            'author' => $publication->getAuthor()->toArray(),
            'fileUrl' => '/api/publications/'.$publication->getId().'/file',
            'createdAt' => $publication->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
