<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A CRM activity logged against a [[Customer]]: a call, meeting, email,
 * note or task. Optionally tied to a [[Contact]] and/or an
 * [[Opportunity]]. Sub-resource of the customer (onDelete CASCADE, hard
 * delete). The customer-detail "timeline" is just these sorted by
 * occurredAt DESC.
 *
 * Every activity carries an open/closed status: completedAt is null
 * while open and set once closed — for all types, not just tasks. For
 * tasks occurredAt is the due date/time (so an open task can be
 * "overdue"); for the other types it is when the event happened.
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(name: 'idx_activity_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_activity_opportunity', columns: ['opportunity_id'])]
class Activity
{
    public const TYPE_CALL = 'call';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_EMAIL = 'email';
    public const TYPE_NOTE = 'note';
    public const TYPE_TASK = 'task';

    public const TYPES = [self::TYPE_CALL, self::TYPE_MEETING, self::TYPE_EMAIL, self::TYPE_NOTE, self::TYPE_TASK];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\ManyToOne(targetEntity: Opportunity::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Opportunity $opportunity = null;

    /** The admin who logged it; null if that user was later removed. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 16)]
    private string $type = self::TYPE_NOTE;

    #[ORM\Column(length: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    /** When it happened (events) or is due (tasks). */
    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    /** When the activity was closed; null while still open (any type). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->occurredAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getOpportunity(): ?Opportunity
    {
        return $this->opportunity;
    }

    public function setOpportunity(?Opportunity $opportunity): static
    {
        $this->opportunity = $opportunity;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = \in_array($type, self::TYPES, true) ? $type : self::TYPE_NOTE;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    /** Open = not yet closed (applies to every activity type). */
    public function isOpen(): bool
    {
        return null === $this->completedAt;
    }

    /** An open task — used for due-date / overdue highlighting. */
    public function isOpenTask(): bool
    {
        return self::TYPE_TASK === $this->type && null === $this->completedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
