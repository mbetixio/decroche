<?php

declare(strict_types=1);

namespace App\Progress;

use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Repository\CallRepository;
use App\Repository\MemberRepository;
use App\Repository\ScenarioRepository;

/**
 * The team board: every member and their figures, the library and its averages, the results by situation, the latest
 * calls.
 */
final readonly class TeamReport
{
    public function __construct(
        private MemberRepository $members,
        private CallRepository $calls,
        private ScenarioRepository $scenarios,
        private ProgressReport $progress,
    ) {
    }

    /**
     * @return array{members: list<array{member: Member, stats: Stats, lastAt: ?\DateTimeImmutable}>, library: list<array{scenario: Scenario, calls: int, average: ?int}>, situations: list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>, recent: list<Call>}
     */
    public function build(): array
    {
        $all = $this->calls->all();
        $byMember = $byScenario = [];
        foreach ($all as $call) {
            $byMember[(int) $call->member->id][] = $call;
            $byScenario[(int) $call->scenario->id][] = $call;
        }
        $members = [];
        foreach ($this->members->team() as $member) {
            $list = $byMember[(int) $member->id] ?? [];
            $members[] = ['member' => $member, 'stats' => $this->progress->summarize($list), 'lastAt' => ($list[0] ?? null)?->startedAt];
        }
        $library = [];
        foreach ($this->scenarios->allLibrary() as $scenario) {
            $calls = $byScenario[(int) $scenario->id] ?? [];
            $scored = array_filter($calls, static fn (Call $c): bool => null !== $c->score);
            $library[] = ['scenario' => $scenario, 'calls' => \count($calls), 'average' => [] === $scored ? null : (int) round(array_sum(array_map(static fn (Call $c): int => (int) $c->score, $scored)) / \count($scored))];
        }
        $done = array_values(array_filter($all, static fn (Call $c): bool => $c->isDone() && null !== $c->score && !$c->isReplay()));

        return ['members' => $members, 'library' => $library, 'situations' => $this->progress->bySituation($done), 'recent' => \array_slice($all, 0, 15)];
    }
}
