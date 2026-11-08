<?php

declare(strict_types=1);

namespace App\Progress;

use App\Entity\Member;
use App\Entity\Mission;
use App\Repository\CallRepository;
use App\Repository\MemberRepository;
use App\Repository\MissionRepository;
use Psr\Clock\ClockInterface;

/**
 * Where everyone stands on their missions.
 */
final readonly class MissionTracker
{
    public function __construct(
        private MissionRepository $missions,
        private MemberRepository $members,
        private CallRepository $calls,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A member's missions and their progress, the unfinished and most urgent first (finished ones stay visible).
     *
     * @return list<array{mission: Mission, done: int, complete: bool, overdue: bool}>
     */
    public function of(Member $member): array
    {
        $history = $this->calls->history($member);
        $rows = array_map(function (Mission $mission) use ($history): array {
            $done = $mission->progress($history);

            return ['mission' => $mission, 'done' => $done, 'complete' => $done >= $mission->calls, 'overdue' => $mission->isOverdue($this->clock->now())];
        }, $this->missions->forMember($member));
        $far = new \DateTimeImmutable('2100-01-01');
        usort($rows, static fn (array $a, array $b): int => [$a['complete'], $a['mission']->dueAt ?? $far] <=> [$b['complete'], $b['mission']->dueAt ?? $far]);

        return $rows;
    }

    /**
     * Every mission, with the progress of each member it concerns (managers only when it was given to them).
     *
     * @return list<array{mission: Mission, members: list<array{member: Member, done: int}>}>
     */
    public function team(): array
    {
        $missions = $this->missions->all();
        if ([] === $missions) {
            return [];
        }
        // The team's calls in one query, by member.
        $histories = [];
        foreach ($this->calls->all() as $call) {
            $histories[(int) $call->member->id][] = $call;
        }
        $team = $this->members->team();
        $rows = [];
        foreach ($missions as $mission) {
            $progress = [];
            foreach ($team as $member) {
                if ($mission->concerns($member) && (null !== $mission->assignee || !$member->isManager())) {
                    $progress[] = ['member' => $member, 'done' => $mission->progress($histories[(int) $member->id] ?? [])];
                }
            }
            $rows[] = ['mission' => $mission, 'members' => $progress];
        }

        return $rows;
    }
}
