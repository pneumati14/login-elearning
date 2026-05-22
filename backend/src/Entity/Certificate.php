<?php

namespace App\Entity;

use App\Repository\CertificateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A certificate awarded to a user for completing a course. One per
 * (user, course) pair; carries a short human-readable code.
 */
#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'certificate')]
#[ORM\UniqueConstraint(name: 'uniq_certificate_user_course', columns: ['user_id', 'course_id'])]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Course::class)]
    #[ORM\JoinColumn(name: 'course_id', nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\Column(length: 16, unique: true)]
    private string $code;

    #[ORM\Column]
    private \DateTimeImmutable $issuedAt;

    public function __construct(User $user, Course $course, string $code)
    {
        $this->user = $user;
        $this->course = $course;
        $this->code = $code;
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCourse(): Course
    {
        return $this->course;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }
}
