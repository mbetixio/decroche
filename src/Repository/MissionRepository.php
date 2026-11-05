<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Mission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mission>
 */
final class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

    /**
     * @return list<Mission> every mission, newest first
     */
    public function all(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }

    /**
     * @return list<Mission> the missions given to this member or to the whole team
     */
    public function forMember(Member $member): array
    {
        return array_values(array_filter($this->all(), static fn (Mission $m): bool => $m->concerns($member)));
    }
}
