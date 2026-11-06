<?php

declare(strict_types=1);

namespace App\Progress;

/**
 * A member's figures: calls, scores, trend, criteria, level and XP, streak, objectives, by situation.
 */
final readonly class Stats
{
    /**
     * @param array<string, array{label: string, average: ?float}>                                        $criteria    the average of each criterion
     * @param list<array{id: ?int, score: int, date: string, title: string}>                                 $scores      the last 20 debriefed calls, oldest first
     * @param list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}> $bySituation
     */
    public function __construct(
        /** Every call started, abandoned ones included. */
        public int $calls,
        /** Debriefed calls (replays aside). */
        public int $done,
        public int $abandoned,
        public ?int $average,
        public ?int $best,
        public ?int $last,
        /** The last three scores against the three before, in points. */
        public ?int $trend,
        public array $criteria,
        /** The key of the weakest criterion: what to work on. */
        public ?string $weakest,
        public ?string $strongest,
        public array $scores,
        public Level $level,
        public int $streak,
        /** Calls whose goal was fully reached. */
        public int $goals,
        public array $bySituation,
        /** The share of hidden brief sections found, in percent. */
        public ?int $discovery,
    ) {
    }

    public function goalRate(): ?int
    {
        return $this->done > 0 ? (int) round($this->goals / $this->done * 100) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'calls' => $this->calls, 'done' => $this->done, 'abandoned' => $this->abandoned, 'average' => $this->average,
            'best' => $this->best, 'last' => $this->last, 'trend' => $this->trend, 'criteria' => $this->criteria,
            'weakest' => $this->weakest, 'strongest' => $this->strongest, 'xp' => $this->level->xp, 'level' => $this->level->toArray(),
            'streak' => $this->streak, 'goals_reached' => $this->goals, 'goal_rate' => $this->goalRate(),
            'by_situation' => $this->bySituation, 'discovery' => $this->discovery,
        ];
    }
}
