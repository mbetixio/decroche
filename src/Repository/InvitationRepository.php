<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * @return list<Invitation> the invitations not used yet, newest first
     */
    public function pending(): array
    {
        return $this->findBy(['acceptedAt' => null], ['createdAt' => 'DESC']);
    }

    /**
     * A pending invitation by its link token.
     */
    public function findPendingByToken(string $token): ?Invitation
    {
        return $this->findOneBy(['token' => $token, 'acceptedAt' => null]);
    }
}
