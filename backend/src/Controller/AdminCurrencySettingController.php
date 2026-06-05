<?php

namespace App\Controller;

use App\Entity\CurrencySetting;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Per-currency display rounding (decimal places). Reading is open to
 * sales staff — every money formatter on the client needs it; changing
 * it is administrators only. Missing rows are created with defaults on
 * first read, so new currencies appear automatically.
 */
#[Route('/api/admin/currency-settings', name: 'api_admin_currency_settings_')]
#[IsGranted('ROLE_SALES')]
final class AdminCurrencySettingController extends AbstractController
{
    /** Sensible defaults when a currency has no row yet. */
    private const DEFAULT_DECIMALS = ['HUF' => 0, 'EUR' => 2, 'USD' => 2];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->serializeAll($this->ensureRows()));
    }

    /**
     * Body: { "settings": [{ "currency": "HUF", "decimals": 0 }, …] }.
     * Unknown currencies are rejected; the full list is returned.
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $items = \is_array($payload) ? ($payload['settings'] ?? null) : null;
        if (!\is_array($items)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $rows = $this->ensureRows();

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
            if (!isset($rows[$currency])) {
                return $this->json(['error' => "Ismeretlen pénznem: {$currency}."], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $decimals = $item['decimals'] ?? null;
            if (!is_numeric($decimals) || (int) $decimals < 0 || (int) $decimals > 4) {
                return $this->json(['error' => 'A tizedesjegyek száma 0 és 4 közötti egész lehet.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $rows[$currency]->setDecimals((int) $decimals);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeAll($rows));
    }

    /**
     * Loads the settings rows, creating any missing currency with its
     * default. Returned keyed by currency code.
     *
     * @return array<string, CurrencySetting>
     */
    private function ensureRows(): array
    {
        $repository = $this->entityManager->getRepository(CurrencySetting::class);
        $rows = [];
        foreach ($repository->findAll() as $row) {
            $rows[$row->getCurrency()] = $row;
        }

        $created = false;
        foreach (Customer::CURRENCIES as $currency) {
            if (!isset($rows[$currency])) {
                $row = (new CurrencySetting($currency))
                    ->setDecimals(self::DEFAULT_DECIMALS[$currency] ?? 2);
                $this->entityManager->persist($row);
                $rows[$currency] = $row;
                $created = true;
            }
        }
        if ($created) {
            $this->entityManager->flush();
        }

        ksort($rows);

        return $rows;
    }

    /**
     * @param array<string, CurrencySetting> $rows
     *
     * @return list<array{currency: string, decimals: int}>
     */
    private function serializeAll(array $rows): array
    {
        return array_values(array_map(
            static fn (CurrencySetting $s): array => [
                'currency' => $s->getCurrency(),
                'decimals' => $s->getDecimals(),
            ],
            $rows,
        ));
    }
}
