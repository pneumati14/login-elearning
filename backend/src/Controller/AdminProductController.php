<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Product catalogue — administrators only. Plain admin-managed config,
 * not localized. Products can be added as line items to opportunities.
 * Hard delete (line items snapshot the name/price, so history survives).
 */
#[Route('/api/admin/products', name: 'api_admin_products_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminProductController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $products,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (Product $p): array => $this->serialize($p),
            $this->products->findAllOrdered(),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $product = new Product();
        $error = $this->apply($product, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json($this->serialize($product), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Product $product, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($product, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($product));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Product $product): JsonResponse
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Product $product, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'A termék neve kötelező.';
        }

        $product
            ->setName($name)
            ->setSku($this->nullableString($payload, 'sku'))
            ->setDescription($this->nullableString($payload, 'description'))
            ->setUnitPrice($this->parseDecimal($payload['unitPrice'] ?? null));

        if (\array_key_exists('currency', $payload)) {
            $product->setCurrency((string) $payload['currency']);
        }
        if (\array_key_exists('isActive', $payload)) {
            $product->setIsActive((bool) $payload['isActive']);
        }
        if (\array_key_exists('validFrom', $payload)) {
            $product->setValidFrom($this->parseDate($payload['validFrom']));
        }
        if (\array_key_exists('validUntil', $payload)) {
            $product->setValidUntil($this->parseDate($payload['validUntil']));
        }

        return null;
    }

    private function parseDecimal(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_string($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], trim($value));
            if ('' === $normalized || !is_numeric($normalized)) {
                return null;
            }

            return $normalized;
        }

        return null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        return false === $date ? null : $date->setTime(0, 0);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        if (!\array_key_exists($key, $payload) || !\is_string($payload[$key])) {
            return null;
        }
        $trimmed = trim($payload[$key]);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Product $p): array
    {
        return [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'sku' => $p->getSku(),
            'description' => $p->getDescription(),
            'unitPrice' => $p->getUnitPrice(),
            'currency' => $p->getCurrency(),
            'isActive' => $p->isActive(),
            'validFrom' => $p->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $p->getValidUntil()?->format('Y-m-d'),
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $p->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
