<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MemberRole;
use App\Repository\InvitationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An invitation to join the team: a personal link, valid until it is used or withdrawn.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
class Invitation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180)]
    public private(set) string $email;

    #[ORM\Column(length: 64, unique: true)]
    public private(set) string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $acceptedAt = null;

    public function __construct(
        string $email,
        #[ORM\Column(enumType: MemberRole::class, length: 20)]
        public private(set) MemberRole $role,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Member $invitedBy,
        \DateTimeImmutable $now,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->token = bin2hex(random_bytes(24));
        $this->createdAt = $now;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->acceptedAt = $now;
    }

    public function isPending(): bool
    {
        return null === $this->acceptedAt;
    }
}
