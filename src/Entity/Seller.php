<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un commercial qui s'entraîne, ou un manager qui gère l'espace de sa société et suit l'équipe.
 */
#[ORM\Entity]
class Seller implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const SELLER = 'seller';
    public const MANAGER = 'manager';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255)]
    public private(set) string $password = '';

    /** L'empreinte de sa clé MCP personnelle (la clé elle-même n'est montrée qu'une fois). */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    public private(set) ?string $mcpKeyHash = null;

    /** Qui il est, en ses mots : son rôle, son expérience, ce qu'il vend, à qui. Les situations s'y adaptent. */
    #[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
    public string $profile = '';

    public function __construct(
        #[ORM\Column(length: 120)]
        public string $name,
        #[ORM\Column(length: 180, unique: true)]
        public private(set) string $email,
        #[ORM\Column(length: 20)]
        public string $role = self::SELLER,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public ?Workspace $workspace = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * L'espace de sa société (tout compte en a un ; seuls les comptes d'avant les espaces en manquaient).
     */
    public function space(): Workspace
    {
        return $this->workspace ?? throw new \LogicException('Ce compte n\'est rattaché à aucun espace.');
    }

    public function hasProfile(): bool
    {
        return '' !== trim($this->profile);
    }

    public function isManager(): bool
    {
        return self::MANAGER === $this->role;
    }

    public function setPassword(string $hash): void
    {
        $this->password = $hash;
    }

    /**
     * Une nouvelle clé MCP : l'ancienne cesse de fonctionner.
     */
    public function newMcpKey(): string
    {
        $key = 'spr_'.bin2hex(random_bytes(20));
        $this->mcpKeyHash = hash('sha256', $key);

        return $key;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->isManager() ? ['ROLE_MANAGER'] : ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function initials(): string
    {
        $words = preg_split('/[\s-]+/', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), \array_slice($words, 0, 2))));
    }
}
