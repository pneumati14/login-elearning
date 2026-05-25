<?php

namespace App\Controller;

use App\Entity\LocalizedText;
use App\Entity\Position;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Open job position management — administrators only. Each text field is
 * bilingual: the request carries { en, hu } objects, English required.
 */
#[Route('/api/admin/positions', name: 'api_admin_positions_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminPositionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $position = new Position();
        $error = $this->apply($position, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($position);
        $this->entityManager->flush();

        return $this->json($this->serialize($position), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Position $position, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($position, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($position));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Position $position): JsonResponse
    {
        $this->entityManager->remove($position);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Applies the payload to the position; returns an error message or null.
     *
     * @param array<string, mixed> $payload
     */
    private function apply(Position $position, array $payload): ?string
    {
        $this->applyLocalized($position->getTitle(), $payload['title'] ?? null);
        $this->applyLocalized($position->getLocation(), $payload['location'] ?? null);
        $this->applyLocalized($position->getType(), $payload['type'] ?? null);
        $this->applyLocalized($position->getSummary(), $payload['summary'] ?? null);

        if ('' === $position->getTitle()->getEn()) {
            return 'A pozíció angol megnevezése kötelező.';
        }

        return null;
    }

    private function applyLocalized(LocalizedText $field, mixed $value): void
    {
        $en = \is_array($value) ? trim((string) ($value['en'] ?? '')) : '';
        $hu = \is_array($value) ? trim((string) ($value['hu'] ?? '')) : '';
        $az = \is_array($value) ? trim((string) ($value['az'] ?? '')) : '';
        $de = \is_array($value) ? trim((string) ($value['de'] ?? '')) : '';
        $pt = \is_array($value) ? trim((string) ($value['pt'] ?? '')) : '';
        $tr = \is_array($value) ? trim((string) ($value['tr'] ?? '')) : '';
        $pl = \is_array($value) ? trim((string) ($value['pl'] ?? '')) : '';
        $field->setEn($en)->setHu($hu)->setAz($az)->setDe($de)->setPt($pt)->setTr($tr)->setPl($pl);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Position $position): array
    {
        return [
            'id' => $position->getId(),
            'title' => $position->getTitle()->toArray(),
            'location' => $position->getLocation()->toArray(),
            'type' => $position->getType()->toArray(),
            'summary' => $position->getSummary()->toArray(),
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
