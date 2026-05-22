<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user's enrolment in a course. One row per (user, course) pair.
 */
#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollment')]
#[ORM\UniqueConstraint(name: 'uniq_enrollment_user_course', columns: ['user_id', 'course_id'])]
class Enrollment
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

    #[ORM\Column]
    private \DateTimeImmutable $enrolledAt;

    public function __construct(User $user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
        $this->enrolledAt = new \DateTimeImmutable();
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

    public function getEnrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }
}
