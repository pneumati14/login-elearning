<?php

namespace App\Controller;

use App\Entity\ProductCategory;
use App\Entity\ProductSubcategory;
use App\Repository\ProductCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Product categories and their sub-categories. Reading the list is open
 * to sales staff (they pick a category when creating a product);
 * configuring it is administrators only. Every sub-category mutation
 * returns the parent category with its re-sorted sub-category list.
 */
#[Route('/api/admin/product-categories', name: 'api_admin_product_categories_')]
#[IsGranted('ROLE_SALES')]
final class AdminProductCategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductCategoryRepository $categories,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map(
            fn (ProductCategory $c): array => self::serializeCategory($c),
            $this->categories->findAllOrdered(),
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A kategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $max = -1;
        foreach ($this->categories->findAll() as $c) {
            $max = max($max, $c->getPosition());
        }

        $category = new ProductCategory();
        $category->setName($name)->setPosition($max + 1);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->json(self::serializeCategory($category), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(ProductCategory $category, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A kategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->setName($name);
        $category->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeCategory($category));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(ProductCategory $category): JsonResponse
    {
        // Products referencing this category (or its sub-categories) keep
        // their history: the product FKs are ON DELETE SET NULL.
        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    // ── Sub-categories ─────────────────────────────────────────────────

    #[Route('/{categoryId<\d+>}/subcategories', name: 'subcategory_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createSubcategory(int $categoryId, Request $request): JsonResponse
    {
        $category = $this->entityManager->find(ProductCategory::class, $categoryId);
        if (!$category instanceof ProductCategory) {
            return $this->json(['error' => 'A kategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'Az alkategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $max = -1;
        foreach ($category->getSubcategories() as $s) {
            $max = max($max, $s->getPosition());
        }

        $subcategory = new ProductSubcategory();
        $subcategory->setCategory($category)->setName($name)->setPosition($max + 1);

        $this->entityManager->persist($subcategory);
        $category->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($category);

        return $this->json(self::serializeCategory($category), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{categoryId<\d+>}/subcategories/{id<\d+>}', name: 'subcategory_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateSubcategory(int $categoryId, int $id, Request $request): JsonResponse
    {
        $subcategory = $this->findSubcategory($categoryId, $id);
        if (null === $subcategory) {
            return $this->json(['error' => 'Az alkategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'Az alkategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subcategory->setName($name);
        $subcategory->getCategory()->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeCategory($subcategory->getCategory()));
    }

    #[Route('/{categoryId<\d+>}/subcategories/{id<\d+>}', name: 'subcategory_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSubcategory(int $categoryId, int $id): JsonResponse
    {
        $subcategory = $this->findSubcategory($categoryId, $id);
        if (null === $subcategory) {
            return $this->json(['error' => 'Az alkategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $category = $subcategory->getCategory();
        $this->entityManager->remove($subcategory);
        $category->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($category);

        return $this->json(self::serializeCategory($category));
    }

    /**
     * Reorder a category's sub-categories. Body: { "order": [id, id, ...] }.
     */
    #[Route('/{categoryId<\d+>}/subcategories/reorder', name: 'subcategory_reorder', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorderSubcategories(int $categoryId, Request $request): JsonResponse
    {
        $category = $this->entityManager->find(ProductCategory::class, $categoryId);
        if (!$category instanceof ProductCategory) {
            return $this->json(['error' => 'A kategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $order = $payload['order'] ?? null;
        if (!\is_array($order)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $position = 0;
        foreach ($order as $subId) {
            $subcategory = $this->findSubcategory($categoryId, (int) $subId);
            if (null !== $subcategory) {
                $subcategory->setPosition($position++);
            }
        }
        $category->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($category);

        return $this->json(self::serializeCategory($category));
    }

    private function findSubcategory(int $categoryId, int $id): ?ProductSubcategory
    {
        $subcategory = $this->entityManager->find(ProductSubcategory::class, $id);
        if (!$subcategory instanceof ProductSubcategory || $subcategory->getCategory()->getId() !== $categoryId) {
            return null;
        }

        return $subcategory;
    }

    /**
     * Shared with the product controller so both emit the same shape.
     *
     * @return array<string, mixed>
     */
    public static function serializeCategory(ProductCategory $c): array
    {
        return [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'position' => $c->getPosition(),
            'subcategories' => array_map(
                fn (ProductSubcategory $s): array => [
                    'id' => $s->getId(),
                    'name' => $s->getName(),
                    'position' => $s->getPosition(),
                ],
                $c->getSubcategories()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
