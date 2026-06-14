<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Note;
use App\Entity\NoteFolder;
use App\Entity\NoteSubmission;
use App\Entity\Opportunity;
use App\Entity\User;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The CRM notes page: private, per-user notes organised into
 * [[NoteFolder]]s. A note can be "sent" to a customer, which copies its
 * content into a customer [[Activity]] (type=note) — editable afterwards
 * on the customer timeline — while the note itself stays put. Each send
 * is recorded as a [[NoteSubmission]] shown on the editor.
 */
#[Route('/api/admin/notes', name: 'api_admin_notes_')]
#[IsGranted('ROLE_SALES')]
final class AdminNoteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteRepository $notes,
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
            fn (Note $n): array => $this->serialize($n),
            $this->notes->findForOwner($owner),
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

        $note = new Note();
        $note->setOwner($owner);

        $error = $this->apply($note, $owner, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return $this->json($this->serialize($note), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $note = $this->findOwnNote($id);
        if (null === $note) {
            return $this->json(['error' => 'A jegyzet nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($note, $note->getOwner(), $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $note->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($note));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $note = $this->findOwnNote($id);
        if (null === $note) {
            return $this->json(['error' => 'A jegyzet nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Send a copy of the note to a customer as an activity (type=note).
     * Body: { customerId, contactId?, opportunityId? }. The note is left
     * untouched; the resulting activity is editable on the timeline.
     */
    #[Route('/{id<\d+>}/send', name: 'send', methods: ['POST'])]
    public function send(int $id, Request $request): JsonResponse
    {
        $owner = $this->currentUser();
        $note = $this->findOwnNote($id);
        if (null === $note || null === $owner) {
            return $this->json(['error' => 'A jegyzet nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $customerId = $payload['customerId'] ?? null;
        if (null === $customerId || '' === $customerId) {
            return $this->json(['error' => 'Az ügyfél kiválasztása kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $customer = $this->entityManager->find(Customer::class, (int) $customerId);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The activity needs a non-empty subject; fall back to the note's
        // first line when it has no title.
        $subject = trim($note->getTitle());
        if ('' === $subject) {
            $subject = $this->firstLine($note->getBody());
        }
        if ('' === $subject) {
            $subject = 'Jegyzet';
        }

        $activity = new Activity();
        $activity->setCustomer($customer)
            ->setCreatedBy($owner)
            ->setType(Activity::TYPE_NOTE)
            ->setSubject(mb_substr($subject, 0, 255))
            ->setBody($note->getBody())
            ->setOccurredAt(new \DateTimeImmutable());

        // Optional contact / opportunity, validated against the customer.
        $contactId = $payload['contactId'] ?? null;
        if (null !== $contactId && '' !== $contactId) {
            $contact = $this->entityManager->find(Contact::class, (int) $contactId);
            if (!$contact instanceof Contact || $contact->getCustomer()->getId() !== $customer->getId()) {
                return $this->json(['error' => 'A kapcsolattartó nem ehhez az ügyfélhez tartozik.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setContact($contact);
        }

        $opportunityId = $payload['opportunityId'] ?? null;
        if (null !== $opportunityId && '' !== $opportunityId) {
            $opportunity = $this->entityManager->find(Opportunity::class, (int) $opportunityId);
            if (!$opportunity instanceof Opportunity || $opportunity->getCustomer()->getId() !== $customer->getId()) {
                return $this->json(['error' => 'A lehetőség nem ehhez az ügyfélhez tartozik.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setOpportunity($opportunity);
        }

        $submission = new NoteSubmission();
        $submission->setNote($note)
            ->setCustomer($customer)
            ->setCustomerName($customer->getName())
            ->setActivity($activity)
            ->setSentBy($owner);

        $this->entityManager->persist($activity);
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        return $this->json($this->serialize($note), JsonResponse::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Note $note, User $owner, array $payload): ?string
    {
        // Title is optional (a note may be body-only); store it trimmed.
        $note->setTitle(mb_substr(trim((string) ($payload['title'] ?? '')), 0, 255));
        $note->setBody($this->nullableString($payload, 'body'));

        if (\array_key_exists('folderId', $payload)) {
            $folderId = $payload['folderId'];
            if (null === $folderId || '' === $folderId) {
                $note->setFolder(null);
            } else {
                $folder = $this->entityManager->find(NoteFolder::class, (int) $folderId);
                if (!$folder instanceof NoteFolder || $folder->getOwner()?->getId() !== $owner->getId()) {
                    return 'A mappa nem található.';
                }
                $note->setFolder($folder);
            }
        }

        return null;
    }

    private function firstLine(?string $body): string
    {
        if (null === $body) {
            return '';
        }
        // The body is now editor HTML; flatten block boundaries to newlines
        // and strip tags so the subject fallback is clean plain text.
        $text = preg_replace('#</(p|h1|h2|h3|li|div|blockquote|pre)>#i', "\n", $body) ?? $body;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $line = strtok(trim($text), "\n");

        return false === $line ? '' : trim($line);
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function findOwnNote(int $id): ?Note
    {
        $owner = $this->currentUser();
        if (null === $owner) {
            return null;
        }
        $note = $this->entityManager->find(Note::class, $id);
        if (!$note instanceof Note || $note->getOwner()?->getId() !== $owner->getId()) {
            return null;
        }

        return $note;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Note $n): array
    {
        $submissions = [];
        foreach ($n->getSubmissions() as $s) {
            $submissions[] = [
                'id' => $s->getId(),
                'customerId' => $s->getCustomer()?->getId(),
                'customerName' => $s->getCustomerName(),
                'activityId' => $s->getActivity()?->getId(),
                'sentAt' => $s->getSentAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return [
            'id' => $n->getId(),
            'title' => $n->getTitle(),
            'body' => $n->getBody(),
            'folderId' => $n->getFolder()?->getId(),
            'submissions' => $submissions,
            'createdAt' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $n->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
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
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }
}
