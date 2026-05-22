<?php

namespace App\Controller;

use App\Entity\Certificate;
use App\Entity\User;
use App\Repository\CertificateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Read access to the signed-in user's course-completion certificates.
 */
final class CertificateController extends AbstractController
{
    public function __construct(private readonly CertificateRepository $certificates)
    {
    }

    #[Route('/api/certificates', name: 'api_certificates_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map(
            $this->serialize(...),
            $this->certificates->findForUser($user),
        ));
    }

    #[Route('/api/certificates/{id<\d+>}', name: 'api_certificates_show', methods: ['GET'])]
    public function show(Certificate $certificate, #[CurrentUser] User $user): JsonResponse
    {
        if ($certificate->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $this->json($this->serialize($certificate));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Certificate $certificate): array
    {
        return [
            'id' => $certificate->getId(),
            'code' => $certificate->getCode(),
            'recipientName' => $certificate->getUser()->getFullName(),
            'courseTitle' => $certificate->getCourse()->getTitle()->toArray(),
            'courseSlug' => $certificate->getCourse()->getSlug(),
            'issuedAt' => $certificate->getIssuedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
