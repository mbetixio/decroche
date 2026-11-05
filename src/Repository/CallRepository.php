<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\CallStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Call>
 */
final class CallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Call::class);
    }

    /**
     * A member's calls, newest first.
     *
     * @return list<Call>
     */
    public function history(Member $member, int $limit = 500): array
    {
        return $this->findBy(['member' => $member], ['startedAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * The call a member is in, or that waits for its debrief.
     */
    public function current(Member $member): ?Call
    {
        return $this->findOneBy(['member' => $member, 'status' => [CallStatus::Live, CallStatus::Debriefing]], ['startedAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * The member's latest call (over MCP, "the last one" when no number is given).
     */
    public function latest(Member $member): ?Call
    {
        return $this->findOneBy(['member' => $member], ['startedAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * Every call of the team, newest first.
     *
     * @return list<Call>
     */
    public function all(int $limit = 2000): array
    {
        return $this->findBy([], ['startedAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * @return list<Call>
     */
    public function ofScenario(Scenario $scenario, Member $member, int $limit = 10): array
    {
        return $this->findBy(['scenario' => $scenario, 'member' => $member], ['startedAt' => 'DESC'], $limit);
    }

    public function save(Call $call): void
    {
        $this->getEntityManager()->persist($call);
        $this->getEntityManager()->flush();
    }
}
