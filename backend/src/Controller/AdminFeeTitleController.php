<?php

namespace App\Controller;

use App\Entity\FeeTitle;
use App\Repository\FeeTitleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fee-title master list. Reading is open to sales staff (they pick a
 * title on the customer's billing tab); managing the list is
 * administrators only. Hard delete — billing references are set null.
 */
#[Route('/api/admin/fee-titles', name: 'api_admin_fee_titles_')]
#[IsGranted('ROLE_SALES')]
final class AdminFeeTitleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FeeTitleRepository $feeTitles,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (FeeTitle $f): array => $this->serialize($f),
            $this->feeTitles->findAllOrdered(),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $title = new FeeTitle();
        $error = $this->apply($title, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($title);
        $this->entityManager->flush();

        return $this->json($this->serialize($title), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(FeeTitle $title, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($title, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $title->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($title));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(FeeTitle $title): JsonResponse
    {
        $this->entityManager->remove($title);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(FeeTitle $title, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'A jogcím megnevezése kötelező.';
        }

        $title->setName($name);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(FeeTitle $f): array
    {
        return [
            'id' => $f->getId(),
            'name' => $f->getName(),
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
