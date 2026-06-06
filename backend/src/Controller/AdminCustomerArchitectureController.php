<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerArchitecture;
use App\Entity\CustomerArchitectureFile;
use App\Entity\Integration;
use App\Repository\CustomerArchitectureFileRepository;
use App\Repository\CustomerArchitectureRepository;
use App\Service\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The customer's architecture tab: deployment model (on-prem / SaaS),
 * SaaS hosting server, VPN/user notes for on-prem, the linked
 * integration catalogue entries and the typed attachments (architecture
 * diagram, system plan, SDD, other — PDF, Word or image in
 * MediaStorage's "architecture" folder). The sheet row is created
 * lazily on first save; every mutation returns the whole block so the
 * client refreshes in one step.
 */
#[Route('/api/admin/customers/{customerId<\d+>}/architecture', name: 'api_admin_customer_architecture_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerArchitectureController extends AbstractController
{
    private const FILE_SUBDIR = 'architecture';

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerArchitectureRepository $architectures,
        private readonly CustomerArchitectureFileRepository $files,
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(int $customerId): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($customer));
    }

    /**
     * Body: { deploymentModel?, saasServer?, vpnInfo?, usersInfo?,
     * notes?, integrationIds?: [] }. Creates the sheet row on first save.
     */
    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $architecture = $this->architectures->findForCustomer($customer);
        if (null === $architecture) {
            $architecture = (new CustomerArchitecture())->setCustomer($customer);
            $this->entityManager->persist($architecture);
        }

        if (\array_key_exists('deploymentModel', $payload)) {
            $raw = $payload['deploymentModel'];
            if (null !== $raw && '' !== $raw && !\in_array($raw, CustomerArchitecture::MODELS, true)) {
                return $this->json(['error' => 'Érvénytelen telepítési modell.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $architecture->setDeploymentModel(\is_string($raw) && '' !== $raw ? $raw : null);
        }
        if (\array_key_exists('saasServer', $payload)) {
            $architecture->setSaasServer($this->nullableString($payload['saasServer']));
        }
        if (\array_key_exists('vpnInfo', $payload)) {
            $architecture->setVpnInfo($this->nullableString($payload['vpnInfo']));
        }
        if (\array_key_exists('usersInfo', $payload)) {
            $architecture->setUsersInfo($this->nullableString($payload['usersInfo']));
        }
        if (\array_key_exists('notes', $payload)) {
            $architecture->setNotes($this->nullableString($payload['notes']));
        }

        if (\array_key_exists('integrationIds', $payload)) {
            $ids = $payload['integrationIds'];
            if (!\is_array($ids)) {
                return $this->json(['error' => 'Érvénytelen integráció-lista.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $architecture->clearIntegrations();
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                $integration = $this->entityManager->find(Integration::class, $id);
                if (!$integration instanceof Integration) {
                    return $this->json(['error' => 'A kiválasztott integráció nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
                }
                $architecture->addIntegration($integration);
            }
        }

        $architecture->touch();
        $customer->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($customer));
    }

    /** Multipart: file + kind (diagram / plan / sdd / other). */
    #[Route('/files', name: 'file_upload', methods: ['POST'])]
    public function uploadFile(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $kind = (string) $request->request->get('kind', '');
        if (!\in_array($kind, CustomerArchitectureFile::KINDS, true)) {
            return $this->json(['error' => 'Érvénytelen dokumentumtípus.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->files->get('file');
        if (null === $file || !$file->isValid()) {
            return $this->json(['error' => 'Hiányzó vagy hibás fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true) && !str_starts_with($mime, 'image/')) {
            return $this->json(
                ['error' => 'Csak PDF, Word vagy kép tölthető fel.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $originalName = $file->getClientOriginalName() ?: 'dokumentum';
        $storedName = $this->storage->store($file, self::FILE_SUBDIR);

        $attachment = (new CustomerArchitectureFile())
            ->setCustomer($customer)
            ->setKind($kind)
            ->setStoredName($storedName)
            ->setOriginalName(mb_substr($originalName, 0, 255))
            ->setMimeType(mb_substr($mime, 0, 100));
        $this->entityManager->persist($attachment);
        $customer->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/files/{fileId<\d+>}', name: 'file_download', methods: ['GET'])]
    public function downloadFile(int $customerId, int $fileId): Response
    {
        $attachment = $this->findFile($customerId, $fileId);
        if (null === $attachment) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $path = $this->storage->path($attachment->getStoredName(), self::FILE_SUBDIR);
        if (!is_file($path)) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment->getMimeType());
        // PDFs and images open in the browser; Word documents download.
        $disposition = 'application/pdf' === $attachment->getMimeType() || str_starts_with($attachment->getMimeType(), 'image/')
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $disposition,
            $attachment->getOriginalName(),
            'dokumentum.'.pathinfo($attachment->getStoredName(), \PATHINFO_EXTENSION),
        ));

        return $response;
    }

    #[Route('/files/{fileId<\d+>}', name: 'file_delete', methods: ['DELETE'])]
    public function deleteFile(int $customerId, int $fileId): JsonResponse
    {
        $attachment = $this->findFile($customerId, $fileId);
        if (null === $attachment) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $attachment->getCustomer();
        $this->storage->delete($attachment->getStoredName(), self::FILE_SUBDIR);
        $this->entityManager->remove($attachment);
        $customer->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($customer));
    }

    private function findCustomer(int $id): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $id);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $customer;
    }

    private function findFile(int $customerId, int $fileId): ?CustomerArchitectureFile
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return null;
        }
        $attachment = $this->entityManager->find(CustomerArchitectureFile::class, $fileId);
        if (!$attachment instanceof CustomerArchitectureFile || $attachment->getCustomer()->getId() !== $customer->getId()) {
            return null;
        }

        return $attachment;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Customer $customer): array
    {
        $architecture = $this->architectures->findForCustomer($customer);

        return [
            'deploymentModel' => $architecture?->getDeploymentModel(),
            'saasServer' => $architecture?->getSaasServer(),
            'vpnInfo' => $architecture?->getVpnInfo(),
            'usersInfo' => $architecture?->getUsersInfo(),
            'notes' => $architecture?->getNotes(),
            'integrationIds' => null === $architecture
                ? []
                : array_map(
                    static fn (Integration $i): int => (int) $i->getId(),
                    $architecture->getIntegrations()->toArray(),
                ),
            'updatedAt' => $architecture?->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'files' => array_map(
                fn (CustomerArchitectureFile $f): array => [
                    'id' => $f->getId(),
                    'kind' => $f->getKind(),
                    'originalName' => $f->getOriginalName(),
                    'mimeType' => $f->getMimeType(),
                    'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'url' => \sprintf('/api/admin/customers/%d/architecture/files/%d', $customer->getId(), $f->getId()),
                ],
                $this->files->findForCustomer($customer),
            ),
        ];
    }
}
