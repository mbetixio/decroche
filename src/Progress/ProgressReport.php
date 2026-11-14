<?php

declare(strict_types=1);

namespace App\Progress;

use App\Call\Coach\Debrief;
use App\Entity\Call;
use App\Entity\Member;
use App\Enum\CallStatus;
use App\Enum\ObjectiveStatus;
use App\Model\Criterion;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;

/**
 * Computes a member's figures from their calls, and the difficulty of their next surprise calls. Replays of a moment
 * do not count.
 */
final readonly class ProgressReport
{
    public function __construct(
        private CallRepository $calls,
        private CompanyRepository $companies,
    ) {
    }

    /**
     * @param list<Call>|null $history the member's calls, newest first, when the page already has them
     */
    public function of(Member $member, ?array $history = null): Stats
    {
        return $this->summarize($history ?? $this->calls->history($member));
    }

    /**
     * @param list<Call> $calls newest first
     */
    public function summarize(array $calls): Stats
    {
        $done = array_values(array_filter($calls, static fn (Call $c): bool => $c->isDone() && null !== $c->score && !$c->isReplay()));
        $scores = array_map(static fn (Call $c): int => (int) $c->score, $done);
        $debriefs = array_map(static fn (Call $c): Debrief => Debrief::fromArray($c->debrief ?? []), $done);

        // The criteria come from the debriefs themselves: if the scorecard changes, the history stays readable.
        $labels = [];
        foreach ($debriefs as $debrief) {
            foreach ($debrief->criteria as $key => $c) {
                $labels[$key] ??= $c['label'];
            }
        }
        if ([] === $labels) {
            $labels = array_map(static fn (Criterion $c): string => $c->label, Criterion::defaults($this->companies->get()->language));
        }
        $criteria = [];
        foreach ($labels as $key => $label) {
            $values = array_values(array_filter(array_map(static fn (Debrief $d): ?int => $d->criteria[$key]['score'] ?? null, $debriefs), static fn (?int $v): bool => null !== $v));
            $criteria[$key] = ['label' => $label, 'average' => [] === $values ? null : round(array_sum($values) / \count($values), 1)];
        }
        $rated = array_filter($criteria, static fn (array $c): bool => null !== $c['average']);
        uasort($rated, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        $trend = \count($scores) >= 4
            ? (int) round(array_sum(\array_slice($scores, 0, 3)) / 3 - array_sum(\array_slice($scores, 3, 3)) / \count(\array_slice($scores, 3, 3)))
            : null;
        $sections = array_sum(array_map(static fn (Call $c): int => \count($c->briefSheet()), $done));

        return new Stats(
            calls: \count($calls),
            done: \count($done),
            abandoned: \count(array_filter($calls, static fn (Call $c): bool => CallStatus::Abandoned === $c->status)),
            average: [] === $scores ? null : (int) round(array_sum($scores) / \count($scores)),
            best: [] === $scores ? null : max($scores),
            last: $scores[0] ?? null,
            trend: $trend,
            criteria: $criteria,
            weakest: array_key_first($rated),
            strongest: array_key_last($rated),
            scores: array_reverse(array_map(static fn (Call $c): array => ['id' => $c->id, 'score' => (int) $c->score, 'date' => $c->startedAt->format('d/m'), 'title' => $c->scenario->title], \array_slice($done, 0, 20))),
            challenge: Challenge::of($scores),
            goals: \count(array_filter($debriefs, static fn (Debrief $d): bool => ObjectiveStatus::Reached === $d->goal)),
            bySituation: $this->bySituation($done),
            discovery: 0 === $sections ? null : (int) round(array_sum(array_map(static fn (Call $c): int => $c->revealedCount(), $done)) / $sections * 100),
        );
    }

    /**
     * The figures by situation: how many calls, their average, the share whose goal was reached.
     *
     * @param list<Call> $done debriefed calls
     *
     * @return list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>
     */
    public function bySituation(array $done): array
    {
        $groups = [];
        foreach ($done as $call) {
            if (null !== $situation = $call->scenario->situation()) {
                $groups[$situation->key][] = $call;
            }
        }
        $rows = [];
        foreach ($groups as $key => $list) {
            $situation = $list[0]->scenario->situation();
            $reached = array_filter($list, static fn (Call $c): bool => ObjectiveStatus::Reached === Debrief::fromArray($c->debrief ?? [])->goal);
            $rows[] = [
                'key' => (string) $key,
                'label' => $situation->label ?? (string) $key,
                'emoji' => $situation->emoji ?? '📞',
                'calls' => \count($list),
                'average' => (int) round(array_sum(array_map(static fn (Call $c): int => (int) $c->score, $list)) / \count($list)),
                'goals' => (int) round(\count($reached) / \count($list) * 100),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['calls'] <=> $a['calls']);

        return $rows;
    }
}
