<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MemberRole;
use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A person of the company: a seller who practises, or a manager who also runs the instance and follows the team.
 */
#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, unique: true)]
    public private(set) string $email;

    #[ORM\Column(length: 255)]
    private string $password = '';

    /** Who they are, in their own words: role, experience, what they sell, to whom. Situations and the coach adapt. */
    #[ORM\Column(type: Types::TEXT)]
    public string $profile = '';

    /** The SHA-256 hash of their personal MCP key (the key itself is shown only once). */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    public private(set) ?string $mcpKeyHash = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        public string $name,
        string $email,
        #[ORM\Column(enumType: MemberRole::class, length: 20)]
        public MemberRole $role = MemberRole::Seller,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    public function isManager(): bool
    {
        return MemberRole::Manager === $this->role;
    }

    public function hasProfile(): bool
    {
        return '' !== trim($this->profile);
    }

    public function setPassword(string $hash): void
    {
        $this->password = $hash;
    }

    /**
     * A new personal MCP key: the previous one stops working.
     */
    public function newMcpKey(): string
    {
        $key = 'spr_'.bin2hex(random_bytes(20));
        $this->mcpKeyHash = hash('sha256', $key);

        return $key;
    }

    public function initials(): string
    {
        $words = preg_split('/[\s-]+/', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), \array_slice($words, 0, 2))));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->role->securityRoles();
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : throw new \LogicException('A member always has an e-mail address.');
    }

    /**
     * Never store the password hash in the session: only a hash of it, so that changing it logs other sessions out.
     *
     * @return array<mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
