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
     * @return list<Mission> every mission, newest first, with its assignee
     */
    public function all(): array
    {
        /* @var list<Mission> */
        return $this->createQueryBuilder('m')
            ->addSelect('a')
            ->leftJoin('m.assignee', 'a')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * @return list<Mission> the missions given to this member or to the whole team
     */
    public function forMember(Member $member): array
    {
        /* @var list<Mission> */
        return $this->createQueryBuilder('m')
            ->where('m.assignee IS NULL OR m.assignee = :member')
            ->setParameter('member', $member)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
