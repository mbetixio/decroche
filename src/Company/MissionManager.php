<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\Member;
use App\Entity\Mission;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Missions given by managers.
 */
final readonly class MissionManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private CompanyRepository $companies,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Gives a mission: so many calls (of a situation of the catalogue, or any) scored at least so much, before a date.
     *
     * @param Member|null $assignee null: the whole team
     */
    public function give(?Member $assignee, ?string $situation, int $calls, int $target, ?\DateTimeImmutable $dueAt, string $note, Member $by): Mission
    {
        $situation = null !== $this->companies->get()->situation($situation) ? $situation : null;
        $mission = new Mission($assignee, $situation, $calls, $target, $dueAt, mb_substr(trim($note), 0, 200), $by, $this->clock->now());
        $this->em->persist($mission);
        $this->em->flush();

        return $mission;
    }

    public function remove(Mission $mission): void
    {
        $this->em->remove($mission);
        $this->em->flush();
    }
}
