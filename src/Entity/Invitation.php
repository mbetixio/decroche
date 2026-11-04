<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une invitation à rejoindre l'espace d'une société : un lien personnel, valable jusqu'à ce qu'il serve ou soit retiré.
 */
#[ORM\Entity]
class Invitation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 64, unique: true)]
    public private(set) string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $acceptedAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Workspace $workspace,
        #[ORM\Column(length: 180)]
        public private(set) string $email,
        #[ORM\Column(length: 20)]
        public private(set) string $role = Seller::SELLER,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Seller $invitedBy = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->token = bin2hex(random_bytes(24));
        $this->createdAt = new \DateTimeImmutable();
    }
}
