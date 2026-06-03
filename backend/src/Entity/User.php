<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // CRM / admin roles, in ascending order of privilege. The role
    // hierarchy in security.yaml makes each one inherit the ones below it.
    public const ROLE_SALES = 'ROLE_SALES';
    public const ROLE_SALES_MANAGER = 'ROLE_SALES_MANAGER';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    /**
     * Map a short "primary role" token (as used by the admin UI and the
     * API payloads) to the stored roles array. Anything unknown — including
     * the plain "user" — maps to no extra role (ROLE_USER is implicit).
     *
     * @return list<string>
     */
    public static function rolesForPrimary(string $token): array
    {
        return match ($token) {
            'admin' => [self::ROLE_ADMIN],
            'sales_manager' => [self::ROLE_SALES_MANAGER],
            'sales' => [self::ROLE_SALES],
            default => [],
        };
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Hashed password. Nullable until authentication is wired up.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    /** Stored filename of an uploaded profile picture. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    /** The user's preferred UI language ('hu', 'en', 'az', 'de', 'pt', 'tr', 'pl' or 'es'). */
    #[ORM\Column(length: 5, options: ['default' => 'hu'])]
    private string $locale = 'hu';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * The unique identifier the security system authenticates against.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * The single highest-privilege role this user holds, as the short token
     * the admin UI works with: 'admin' | 'sales_manager' | 'sales' | 'user'.
     */
    public function getPrimaryRole(): string
    {
        $roles = $this->getRoles();
        if (\in_array(self::ROLE_ADMIN, $roles, true)) {
            return 'admin';
        }
        if (\in_array(self::ROLE_SALES_MANAGER, $roles, true)) {
            return 'sales_manager';
        }
        if (\in_array(self::ROLE_SALES, $roles, true)) {
            return 'sales';
        }

        return 'user';
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * No sensitive plain-text data is held on the entity, so there is
     * nothing to erase. Required by UserInterface.
     */
    public function eraseCredentials(): void
    {
    }
}
