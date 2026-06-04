<?php

namespace App\Controller;

use App\Entity\BillingItem;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Opportunity;
use App\Entity\OpportunityDocument;
use App\Entity\OpportunityLineItem;
use App\Entity\OpportunityStage;
use App\Entity\OpportunityStageChange;
use App\Entity\OpportunityType;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\BillingItemRepository;
use App\Repository\OpportunityRepository;
use App\Service\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: sales opportunities (deals). Administrators
 * only. Hard delete; opportunities are removed with their customer
 * (onDelete CASCADE on the entity). Each opportunity belongs to a fixed
 * OpportunityType and moves between that type's stages; every stage move
 * is recorded as an OpportunityStageChange.
 */
#[Route('/api/admin/customers/{customerId<\d+>}/opportunities', name: 'api_admin_customer_opportunities_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerOpportunityController extends AbstractController
{
    private const DOC_SUBDIR = 'opportunities';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OpportunityRepository $opportunities,
        private readonly BillingItemRepository $billingItems,
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $customerId): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = array_map(
            fn (Opportunity $o): array => $this->serialize($o),
            $this->opportunities->findForCustomer($customer),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ('' === $title) {
            return $this->json(['error' => 'A lehetőség neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type = $this->entityManager->find(OpportunityType::class, (int) ($payload['typeId'] ?? 0));
        if (!$type instanceof OpportunityType) {
            return $this->json(['error' => 'A kiválasztott típus nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stage = $this->resolveStage($type, $payload['stageId'] ?? null);
        if (null === $stage) {
            return $this->json(['error' => 'A típushoz nincs használható fázis.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $opportunity = new Opportunity();
        $opportunity->setCustomer($customer)->setType($type)->setTitle($title)->setStage($stage);

        $error = $this->applyFields($opportunity, $customer, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Initial history entry: created directly in its first stage.
        $opportunity->addStageChange($this->makeChange(null, $stage->getName()));

        $this->entityManager->persist($opportunity);
        // A deal created straight into a won stage is billed right away.
        if (OpportunityStage::OUTCOME_WON === $stage->getOutcome()) {
            $this->snapshotBillingItems($opportunity);
        }
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $opportunity = $this->findOpportunity($customerId, $id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A lehetőség nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ('' === $title) {
            return $this->json(['error' => 'A lehetőség neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $opportunity->setTitle($title);
        $error = $this->applyFields($opportunity, $opportunity->getCustomer(), $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $opportunity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity));
    }

    /**
     * Move the opportunity to another stage of its pipeline (the kanban
     * drag-and-drop). Body: { "stageId": <id> }. Records a stage change.
     */
    #[Route('/{id<\d+>}/stage', name: 'move_stage', methods: ['PUT'])]
    public function moveStage(int $customerId, int $id, Request $request): JsonResponse
    {
        $opportunity = $this->findOpportunity($customerId, $id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A lehetőség nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $stage = $this->resolveStage($opportunity->getType(), $payload['stageId'] ?? null);
        if (null === $stage) {
            return $this->json(['error' => 'A fázis nem tartozik ehhez a folyamathoz.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $current = $opportunity->getStage();
        if ($stage->getId() !== $current->getId()) {
            $opportunity->addStageChange($this->makeChange($current->getName(), $stage->getName()));
            $opportunity->setStage($stage);
            $opportunity->touch();
            // Winning the deal puts its lines on the billing table.
            if (OpportunityStage::OUTCOME_WON === $stage->getOutcome()
                && OpportunityStage::OUTCOME_WON !== $current->getOutcome()) {
                $this->snapshotBillingItems($opportunity);
            }
            $this->entityManager->flush();
        }

        return $this->json($this->serialize($opportunity));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $opportunity = $this->findOpportunity($customerId, $id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A lehetőség nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Remove the documents' files from disk before the rows cascade away.
        foreach ($opportunity->getDocuments() as $doc) {
            $this->storage->delete($doc->getStoredName(), self::DOC_SUBDIR);
        }

        $this->entityManager->remove($opportunity);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    // ── Offer documents (PDFs) ────────────────────────────────────────

    #[Route('/{id<\d+>}/documents', name: 'document_upload', methods: ['POST'])]
    public function uploadDocument(int $customerId, int $id, Request $request): JsonResponse
    {
        $opportunity = $this->findOpportunity($customerId, $id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A lehetőség nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if ('application/pdf' !== $file->getMimeType()) {
            return $this->json(['error' => 'A feltöltött fájl nem PDF.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Capture metadata before move() invalidates the uploaded file.
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $document = new OpportunityDocument();
        $document->setStoredName($this->storage->store($file, self::DOC_SUBDIR))
            ->setOriginalName('' !== trim($originalName) ? $originalName : 'ajanlat.pdf')
            ->setSize(false === $size ? null : $size);
        $user = $this->getUser();
        if ($user instanceof User) {
            $document->setUploadedBy($user);
        }
        $opportunity->addDocument($document);
        $opportunity->touch();

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/documents/{docId<\d+>}', name: 'document_download', methods: ['GET'])]
    public function downloadDocument(int $customerId, int $id, int $docId): BinaryFileResponse|JsonResponse
    {
        $document = $this->findDocument($customerId, $id, $docId);
        if (null === $document) {
            return $this->json(['error' => 'A dokumentum nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $path = $this->storage->path($document->getStoredName(), self::DOC_SUBDIR);
        if (!is_file($path)) {
            return $this->json(['error' => 'A dokumentum fájlja hiányzik.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->getOriginalName());

        return $response;
    }

    #[Route('/{id<\d+>}/documents/{docId<\d+>}', name: 'document_delete', methods: ['DELETE'])]
    public function deleteDocument(int $customerId, int $id, int $docId): JsonResponse
    {
        $document = $this->findDocument($customerId, $id, $docId);
        if (null === $document) {
            return $this->json(['error' => 'A dokumentum nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $opportunity = $document->getOpportunity();
        $this->storage->delete($document->getStoredName(), self::DOC_SUBDIR);
        $opportunity->removeDocument($document);
        $this->entityManager->remove($document);
        $opportunity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity));
    }

    private function findDocument(int $customerId, int $id, int $docId): ?OpportunityDocument
    {
        $opportunity = $this->findOpportunity($customerId, $id);
        if (null === $opportunity) {
            return null;
        }
        $document = $this->entityManager->find(OpportunityDocument::class, $docId);
        if (!$document instanceof OpportunityDocument || $document->getOpportunity()->getId() !== $opportunity->getId()) {
            return null;
        }

        return $document;
    }

    /**
     * Apply the editable non-stage fields. Returns an error string or null.
     *
     * @param array<string, mixed> $payload
     */
    private function applyFields(Opportunity $o, Customer $customer, array $payload): ?string
    {
        if (\array_key_exists('quoteNumber', $payload)) {
            $o->setQuoteNumber($this->nullableString($payload, 'quoteNumber'));
        }
        if (\array_key_exists('value', $payload)) {
            $o->setValue($this->parseDecimal($payload['value']));
        }
        if (\array_key_exists('currency', $payload)) {
            $o->setCurrency((string) $payload['currency']);
        }
        if (\array_key_exists('expectedCloseDate', $payload)) {
            $o->setExpectedCloseDate($this->parseDate($payload['expectedCloseDate']));
        }
        if (\array_key_exists('notes', $payload)) {
            $o->setNotes($this->nullableString($payload, 'notes'));
        }
        if (\array_key_exists('contactId', $payload)) {
            $contactId = $payload['contactId'];
            if (null === $contactId || '' === $contactId) {
                $o->setContact(null);
            } else {
                $contact = $this->entityManager->find(Contact::class, (int) $contactId);
                if (!$contact instanceof Contact || $contact->getCustomer()->getId() !== $customer->getId()) {
                    return 'A kapcsolattartó nem ehhez az ügyfélhez tartozik.';
                }
                $o->setContact($contact);
            }
        }

        if (\array_key_exists('lineItems', $payload)) {
            $error = $this->applyLineItems($o, $payload['lineItems']);
            if (null !== $error) {
                return $error;
            }
        }

        // The value is driven by the line items whenever there are any;
        // the manual value field only applies to lineless opportunities.
        if (!$o->getLineItems()->isEmpty()) {
            $o->setValue($o->getLineItemsTotal());
        }

        return null;
    }

    /**
     * Rebuild the opportunity's line items from the payload list. Each
     * entry: { productId?, productName, quantity, unitPrice }. The product
     * link is optional; the name is snapshotted (defaulting to the catalogue
     * product's name when omitted).
     */
    private function applyLineItems(Opportunity $o, mixed $items): ?string
    {
        if (!\is_array($items)) {
            return 'Érvénytelen tételsorok.';
        }

        $o->clearLineItems();
        $position = 0;
        foreach ($items as $raw) {
            if (!\is_array($raw)) {
                continue;
            }

            $product = null;
            $productId = $raw['productId'] ?? null;
            if (null !== $productId && '' !== $productId) {
                $product = $this->entityManager->find(Product::class, (int) $productId);
                if (!$product instanceof Product) {
                    return 'A kiválasztott termék nem található.';
                }
            }

            $name = trim((string) ($raw['productName'] ?? ''));
            if ('' === $name && null !== $product) {
                $name = $product->getName();
            }
            if ('' === $name) {
                return 'A tételsor megnevezése kötelező.';
            }

            $item = new OpportunityLineItem();
            $item->setProduct($product)
                ->setProductName($name)
                ->setQuantity($this->parseDecimal($raw['quantity'] ?? null) ?? '1')
                ->setUnitPrice($this->parseDecimal($raw['unitPrice'] ?? null) ?? '0')
                ->setPosition($position++);
            $o->addLineItem($item);
        }

        return null;
    }

    /**
     * Resolve a stage of the given type. With no id (or a bad one on
     * create) fall back to the type's first stage. Returns null when the
     * id names a stage of another type, or the type has no stages.
     */
    private function resolveStage(OpportunityType $type, mixed $stageId): ?OpportunityStage
    {
        if (null !== $stageId && '' !== $stageId) {
            $stage = $this->entityManager->find(OpportunityStage::class, (int) $stageId);
            if ($stage instanceof OpportunityStage && $stage->getType()->getId() === $type->getId()) {
                return $stage;
            }

            return null;
        }

        return $type->getStages()->first() ?: null;
    }

    /**
     * Snapshot the deal's quote lines as billing items (a lineless deal
     * becomes one item from its title and value). Skipped when the deal
     * already produced billing items — a reopened-then-rewon deal must
     * not duplicate its rows. The caller flushes.
     */
    private function snapshotBillingItems(Opportunity $opportunity): void
    {
        if (null !== $opportunity->getId() && $this->billingItems->existsForOpportunity($opportunity)) {
            return;
        }

        $wonAt = $opportunity->getClosedAt() ?? new \DateTimeImmutable('today');
        $lines = $opportunity->getLineItems();
        if ($lines->isEmpty()) {
            $item = (new BillingItem())
                ->setCustomer($opportunity->getCustomer())
                ->setOpportunity($opportunity)
                ->setName($opportunity->getTitle())
                ->setUnitPrice($opportunity->getValue() ?? '0')
                ->setCurrency($opportunity->getCurrency())
                ->setWonAt($wonAt);
            $this->entityManager->persist($item);

            return;
        }

        foreach ($lines as $line) {
            $item = (new BillingItem())
                ->setCustomer($opportunity->getCustomer())
                ->setOpportunity($opportunity)
                ->setName($line->getProductName())
                ->setQuantity($line->getQuantity())
                ->setUnitPrice($line->getUnitPrice())
                ->setCurrency($opportunity->getCurrency())
                ->setWonAt($wonAt);
            $this->entityManager->persist($item);
        }
    }

    private function makeChange(?string $from, string $to): OpportunityStageChange
    {
        $change = new OpportunityStageChange();
        $change->setFromStageName($from)->setToStageName($to);
        $user = $this->getUser();
        if ($user instanceof User) {
            $change->setChangedBy($user);
        }

        return $change;
    }

    private function findCustomer(int $id): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $id);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $customer;
    }

    private function findOpportunity(int $customerId, int $id): ?Opportunity
    {
        $opportunity = $this->entityManager->find(Opportunity::class, $id);
        if (!$opportunity instanceof Opportunity) {
            return null;
        }
        // Guard against URL tampering reaching a sibling customer's row.
        if ($opportunity->getCustomer()->getId() !== $customerId) {
            return null;
        }
        if (null !== $opportunity->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $opportunity;
    }

    /** Decimal as a clean string, or null for empty/invalid input. */
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
     * The salesperson responsible today, derived from the customer's
     * current sales assignment (the first one whose period covers today).
     *
     * @return array{id: int|null, name: string|null}
     */
    private function currentOwner(Customer $customer): array
    {
        $today = new \DateTimeImmutable('today');
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

    /**
     * @return array<string, mixed>
     */
    private function serialize(Opportunity $o): array
    {
        $stage = $o->getStage();
        $contact = $o->getContact();
        $owner = $this->currentOwner($o->getCustomer());

        return [
            'id' => $o->getId(),
            'title' => $o->getTitle(),
            'quoteNumber' => $o->getQuoteNumber(),
            'value' => $o->getValue(),
            'currency' => $o->getCurrency(),
            'expectedCloseDate' => $o->getExpectedCloseDate()?->format('Y-m-d'),
            'closedAt' => $o->getClosedAt()?->format(\DateTimeInterface::ATOM),
            'notes' => $o->getNotes(),
            'typeId' => $o->getType()->getId(),
            'typeName' => $o->getType()->getName(),
            'stageId' => $stage->getId(),
            'stageName' => $stage->getName(),
            'stageOutcome' => $stage->getOutcome(),
            'contactId' => $contact?->getId(),
            'contactName' => null === $contact ? null : (trim($contact->getLastName().' '.$contact->getFirstName()) ?: $contact->getEmail()),
            'ownerId' => $owner['id'],
            'ownerName' => $owner['name'],
            'hasLineItems' => !$o->getLineItems()->isEmpty(),
            'lineItemsTotal' => $o->getLineItemsTotal(),
            'lineItems' => array_map(
                fn (OpportunityLineItem $li): array => [
                    'id' => $li->getId(),
                    'productId' => $li->getProduct()?->getId(),
                    'productName' => $li->getProductName(),
                    'quantity' => $li->getQuantity(),
                    'unitPrice' => $li->getUnitPrice(),
                    'lineTotal' => $li->getLineTotal(),
                ],
                $o->getLineItems()->toArray(),
            ),
            'documents' => array_map(
                fn (OpportunityDocument $d): array => [
                    'id' => $d->getId(),
                    'originalName' => $d->getOriginalName(),
                    'size' => $d->getSize(),
                    'uploadedAt' => $d->getUploadedAt()->format(\DateTimeInterface::ATOM),
                    'uploadedByName' => null === $d->getUploadedBy()
                        ? null
                        : (trim($d->getUploadedBy()->getFirstName().' '.$d->getUploadedBy()->getLastName()) ?: $d->getUploadedBy()->getEmail()),
                    'url' => \sprintf('/api/admin/customers/%d/opportunities/%d/documents/%d', $o->getCustomer()->getId(), $o->getId(), $d->getId()),
                ],
                $o->getDocuments()->toArray(),
            ),
            'stageChanges' => array_map(
                fn (OpportunityStageChange $c): array => [
                    'id' => $c->getId(),
                    'fromStageName' => $c->getFromStageName(),
                    'toStageName' => $c->getToStageName(),
                    'changedByName' => null === $c->getChangedBy()
                        ? null
                        : (trim($c->getChangedBy()->getFirstName().' '.$c->getChangedBy()->getLastName()) ?: $c->getChangedBy()->getEmail()),
                    'changedAt' => $c->getChangedAt()->format(\DateTimeInterface::ATOM),
                ],
                $o->getStageChanges()->toArray(),
            ),
            'createdAt' => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $o->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
