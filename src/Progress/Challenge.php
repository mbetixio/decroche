<?php

declare(strict_types=1);

namespace App\Progress;

use App\Enum\Difficulty;

/**
 * How hard a member's next surprise calls are, and why: it follows their recent scores rather than an accumulation of
 * points, so it goes down again after a few weak calls. Shown to the member so that they know where they stand.
 */
final readonly class Challenge
{
    /** How many recent calls the difficulty looks at. */
    public const int WINDOW = 5;

    /** Below this many graded calls, the member is still warming up. */
    public const int WARM_UP = 3;

    public function __construct(
        public Difficulty $difficulty,
        /** The average of the recent graded calls; null while warming up. */
        public ?int $recentAverage,
        /** How many recent graded calls it was computed from. */
        public int $basis,
    ) {
    }

    /**
     * @param list<int> $scores the graded calls' scores, newest first (replays aside)
     */
    public static function of(array $scores): self
    {
        $recent = \array_slice($scores, 0, self::WINDOW);
        if (\count($recent) < self::WARM_UP) {
            return new self(Difficulty::Approachable, null, \count($recent));
        }
        $average = (int) round(array_sum($recent) / \count($recent));

        return new self(match (true) {
            $average >= 70 => Difficulty::Tough,
            $average >= 50 => Difficulty::Demanding,
            default => Difficulty::Approachable,
        }, $average, \count($recent));
    }

    public function isWarmingUp(): bool
    {
        return null === $this->recentAverage;
    }

    /**
     * @return array{difficulty: string, recent_average: ?int, basis: int}
     */
    public function toArray(): array
    {
        return ['difficulty' => $this->difficulty->name, 'recent_average' => $this->recentAverage, 'basis' => $this->basis];
    }
}
