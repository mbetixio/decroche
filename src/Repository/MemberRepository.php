<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Member>
 */
final class MemberRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Member::class);
    }

    public function findOneByEmail(string $email): ?Member
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findOneByMcpKey(string $key): ?Member
    {
        return '' === $key ? null : $this->findOneBy(['mcpKeyHash' => hash('sha256', $key)]);
    }

    /**
     * @return list<Member> the whole team, by name
     */
    public function team(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function hasAny(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Rehashes a password when the hashing algorithm improves.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Member) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
