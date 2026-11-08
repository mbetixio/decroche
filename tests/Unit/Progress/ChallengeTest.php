<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progress;

use App\Enum\Difficulty;
use App\Progress\Challenge;
use PHPUnit\Framework\TestCase;

/**
 * The difficulty of the next surprise calls: easy while warming up, then following the average of the last five scores,
 * so that it also goes down after weak calls.
 */
final class ChallengeTest extends TestCase
{
    public function testWarmingUp(): void
    {
        $challenge = Challenge::of([95, 90]);

        self::assertSame(Difficulty::Easy, $challenge->difficulty);
        self::assertTrue($challenge->isWarmingUp());
        self::assertNull($challenge->recentAverage);
        self::assertSame(2, $challenge->basis);
    }

    public function testTheRecentAverageSetsTheDifficulty(): void
    {
        self::assertSame(Difficulty::Easy, Challenge::of([40, 45, 48])->difficulty);
        self::assertSame(Difficulty::Demanding, Challenge::of([50, 55, 60])->difficulty);
        self::assertSame(Difficulty::Tough, Challenge::of([70, 75, 80])->difficulty);
    }

    public function testOnlyTheLastFiveCallsCount(): void
    {
        // Newest first: five strong recent calls outweigh a weak start.
        $challenge = Challenge::of([80, 80, 80, 80, 80, 10, 10, 10]);

        self::assertSame(80, $challenge->recentAverage);
        self::assertSame(5, $challenge->basis);
        self::assertSame(Difficulty::Tough, $challenge->difficulty);
    }

    public function testItGoesDownAgainAfterWeakCalls(): void
    {
        // A tough run, then four weak calls (newest first): the average of the last five falls under 50.
        self::assertSame(Difficulty::Easy, Challenge::of([30, 35, 40, 45, 90, 90, 90])->difficulty);
    }

    public function testToArray(): void
    {
        self::assertSame(['difficulty' => 'Demanding', 'recent_average' => 60, 'basis' => 3], Challenge::of([60, 60, 60])->toArray());
    }
}
