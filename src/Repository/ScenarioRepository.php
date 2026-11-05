<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\MeetingType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scenario>
 */
final class ScenarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scenario::class);
    }

    /**
     * The library: active scenarios written for the team (not surprise calls), the ones this member may see.
     *
     * @return list<Scenario>
     */
    public function library(Member $member, ?MeetingType $type = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.active = true')
            ->andWhere('s.situation IS NULL')
            ->orderBy('s.difficulty', 'ASC')
            ->addOrderBy('s.title', 'ASC');
        if (null !== $type) {
            $qb->andWhere('s.type = :type')->setParameter('type', $type);
        }

        return array_values(array_filter($qb->getQuery()->getResult(), static fn (Scenario $s): bool => $s->visibleTo($member)));
    }

    /**
     * Every library scenario, archived ones included (for the team board).
     *
     * @return list<Scenario>
     */
    public function allLibrary(): array
    {
        return $this->createQueryBuilder('s')->where('s.situation IS NULL')->orderBy('s.title', 'ASC')->getQuery()->getResult();
    }

    /**
     * The surprise calls recently drawn for a member, newest first.
     *
     * @return list<Scenario>
     */
    public function recentSurpriseCalls(Member $member, int $limit = 12): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.author = :member')
            ->andWhere('s.situation IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function findOneByTitle(string $title): ?Scenario
    {
        return $this->findOneBy(['title' => $title, 'author' => null]);
    }

    public function save(Scenario $scenario): void
    {
        $this->getEntityManager()->persist($scenario);
        $this->getEntityManager()->flush();
    }
}
