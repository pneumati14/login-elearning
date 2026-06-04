<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Opportunity;
use App\Entity\OpportunityStage;
use App\Entity\OpportunityType;
use App\Repository\OpportunityRepository;
use App\Repository\OpportunityTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pipeline report + forecast for the CRM. Aggregates the opportunities
 * of non-deleted customers:
 *  - funnel: per pipeline type, per stage — deal count and value /
 *    probability-weighted value, broken down by currency (no FX
 *    conversion, each currency is its own row);
 *  - forecast: open deals grouped by the month of their expected close
 *    date; deals without a date are only counted, not forecast;
 *  - closed: won/lost totals for the last 12 months.
 * Also returns an `owners` breakdown: open-deal totals per responsible
 * salesperson. Optional filters: typeId (one pipeline), userId (the
 * salesperson currently responsible, derived from the active sales
 * assignment) and stageIds (comma-separated stage ids — only deals
 * currently sitting in one of them).
 */
#[Route('/api/admin/reports', name: 'api_admin_reports_')]
#[IsGranted('ROLE_SALES')]
final class AdminReportController extends AbstractController
{
    public function __construct(
        private readonly OpportunityRepository $opportunities,
        private readonly OpportunityTypeRepository $types,
    ) {
    }

    #[Route('/pipeline', name: 'pipeline', methods: ['GET'])]
    public function pipeline(Request $request): JsonResponse
    {
        $typeId = $request->query->getInt('typeId') ?: null;
        $userId = $request->query->getInt('userId') ?: null;
        $today = new \DateTimeImmutable('today');

        $qb = $this->opportunities->createQueryBuilder('o')
            ->addSelect('s', 't', 'c')
            ->join('o.stage', 's')
            ->join('o.type', 't')
            ->join('o.customer', 'c')
            ->andWhere('c.deletedAt IS NULL');
        if (null !== $typeId) {
            $qb->andWhere('t.id = :typeId')->setParameter('typeId', $typeId);
        }
        /** @var Opportunity[] $all */
        $all = $qb->getQuery()->getResult();

        if (null !== $userId) {
            $all = array_values(array_filter(
                $all,
                fn (Opportunity $o): bool => $this->currentOwner($o->getCustomer(), $today)['id'] === $userId,
            ));
        }

        $stageIds = array_values(array_filter(array_map('intval', explode(',', (string) $request->query->get('stageIds', '')))));
        if ([] !== $stageIds) {
            $all = array_values(array_filter(
                $all,
                fn (Opportunity $o): bool => \in_array($o->getStage()->getId(), $stageIds, true),
            ));
        }

        // ── Funnel: deals by current stage ──────────────────────────────
        $byStage = [];
        foreach ($all as $o) {
            $byStage[$o->getStage()->getId()][] = $o;
        }

        $typeList = null === $typeId
            ? $this->types->findAllOrdered()
            : array_values(array_filter($this->types->findAllOrdered(), fn (OpportunityType $t): bool => $t->getId() === $typeId));

        $typesData = [];
        foreach ($typeList as $type) {
            $stagesData = [];
            $openTotals = [];
            $openCount = 0;
            foreach ($type->getStages() as $stage) {
                $deals = $byStage[$stage->getId()] ?? [];
                $totals = [];
                foreach ($deals as $o) {
                    $this->accumulate($totals, $o, $stage->getProbability());
                }
                if (OpportunityStage::OUTCOME_OPEN === $stage->getOutcome()) {
                    $openCount += \count($deals);
                    foreach ($deals as $o) {
                        $this->accumulate($openTotals, $o, $stage->getProbability());
                    }
                }
                $stagesData[] = [
                    'id' => $stage->getId(),
                    'name' => $stage->getName(),
                    'outcome' => $stage->getOutcome(),
                    'probability' => $stage->getProbability(),
                    'count' => \count($deals),
                    'totals' => $this->totalsList($totals),
                ];
            }
            $typesData[] = [
                'id' => $type->getId(),
                'name' => $type->getName(),
                'isActive' => $type->isActive(),
                'stages' => $stagesData,
                'openCount' => $openCount,
                'openTotals' => $this->totalsList($openTotals),
            ];
        }

        // ── Forecast: open deals by expected-close month ────────────────
        $months = [];
        $noDateCount = 0;
        foreach ($all as $o) {
            if (OpportunityStage::OUTCOME_OPEN !== $o->getStage()->getOutcome()) {
                continue;
            }
            $date = $o->getExpectedCloseDate();
            if (null === $date) {
                ++$noDateCount;
                continue;
            }
            $key = $date->format('Y-m');
            $months[$key] ??= ['month' => $key, 'count' => 0, 'totals' => []];
            ++$months[$key]['count'];
            $this->accumulate($months[$key]['totals'], $o, $o->getStage()->getProbability());
        }
        ksort($months);
        $forecastMonths = array_map(
            fn (array $m): array => ['month' => $m['month'], 'count' => $m['count'], 'totals' => $this->totalsList($m['totals'])],
            array_values($months),
        );

        // ── Open-deal totals per responsible salesperson ────────────────
        $owners = [];
        foreach ($all as $o) {
            if (OpportunityStage::OUTCOME_OPEN !== $o->getStage()->getOutcome()) {
                continue;
            }
            $owner = $this->currentOwner($o->getCustomer(), $today);
            $key = $owner['id'] ?? 0; // 0 = customers without an active assignment
            $owners[$key] ??= ['id' => $owner['id'], 'name' => $owner['name'], 'count' => 0, 'totals' => []];
            ++$owners[$key]['count'];
            $this->accumulate($owners[$key]['totals'], $o, $o->getStage()->getProbability());
        }
        $ownersData = array_map(
            fn (array $row): array => [
                'id' => $row['id'],
                'name' => $row['name'],
                'count' => $row['count'],
                'totals' => $this->totalsList($row['totals']),
            ],
            array_values($owners),
        );
        usort($ownersData, fn (array $a, array $b): int => strcoll($a['name'] ?? "\u{ffff}", $b['name'] ?? "\u{ffff}"));

        // ── Closed deals in the last 12 months ──────────────────────────
        $closedFrom = $today->modify('-12 months');
        $closed = [
            OpportunityStage::OUTCOME_WON => ['count' => 0, 'totals' => []],
            OpportunityStage::OUTCOME_LOST => ['count' => 0, 'totals' => []],
        ];
        foreach ($all as $o) {
            $outcome = $o->getStage()->getOutcome();
            $closedAt = $o->getClosedAt();
            if (OpportunityStage::OUTCOME_OPEN === $outcome || null === $closedAt || $closedAt < $closedFrom) {
                continue;
            }
            ++$closed[$outcome]['count'];
            // Closed deals count at face value: won is realised, lost is missed.
            $this->accumulate($closed[$outcome]['totals'], $o, 100);
        }

        return $this->json([
            'types' => $typesData,
            'forecast' => [
                'months' => $forecastMonths,
                'noDateCount' => $noDateCount,
            ],
            'owners' => $ownersData,
            'closed' => [
                'from' => $closedFrom->format('Y-m-d'),
                'won' => ['count' => $closed['won']['count'], 'totals' => $this->totalsList($closed['won']['totals'])],
                'lost' => ['count' => $closed['lost']['count'], 'totals' => $this->totalsList($closed['lost']['totals'])],
            ],
        ]);
    }

    /**
     * Add one deal's effective value to a currency-keyed running total.
     * The effective value is the line-items sum when lines exist,
     * otherwise the manual value; a deal without either contributes 0.
     *
     * @param array<string, array{value: float, weighted: float}> $totals
     */
    private function accumulate(array &$totals, Opportunity $o, int $probability): void
    {
        $value = $o->getLineItems()->isEmpty() ? (float) ($o->getValue() ?? 0) : (float) $o->getLineItemsTotal();
        $currency = $o->getCurrency();
        $totals[$currency] ??= ['value' => 0.0, 'weighted' => 0.0];
        $totals[$currency]['value'] += $value;
        $totals[$currency]['weighted'] += $value * $probability / 100;
    }

    /**
     * @param array<string, array{value: float, weighted: float}> $totals
     *
     * @return list<array{currency: string, value: string, weighted: string}>
     */
    private function totalsList(array $totals): array
    {
        ksort($totals);
        $list = [];
        foreach ($totals as $currency => $sums) {
            $list[] = [
                'currency' => $currency,
                'value' => number_format($sums['value'], 2, '.', ''),
                'weighted' => number_format($sums['weighted'], 2, '.', ''),
            ];
        }

        return $list;
    }

    /**
     * The salesperson currently responsible for the customer.
     *
     * @return array{id: int|null, name: string|null}
     */
    private function currentOwner(Customer $customer, \DateTimeImmutable $today): array
    {
        foreach ($customer->getSalesAssignments() as $assignment) {
            if ($assignment->isActiveOn($today)) {
                $user = $assignment->getUser();

                return [
                    'id' => $user->getId(),
                    'name' => trim($user->getFirstName().' '.$user->getLastName()) ?: $user->getEmail(),
                ];
            }
        }

        return ['id' => null, 'name' => null];
    }
}
