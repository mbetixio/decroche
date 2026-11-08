<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progress;

use App\Progress\Level;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A level from experience points: its thresholds, the next one, and the progress towards it.
 */
final class LevelTest extends TestCase
{
    /**
     * @return iterable<array{int, int}>
     */
    public static function thresholds(): iterable
    {
        yield [-5, 1];
        yield [0, 1];
        yield [149, 1];
        yield [150, 2];
        yield [399, 2];
        yield [400, 3];
        yield [800, 4];
        yield [1399, 4];
        yield [1400, 5];
        yield [2200, 6];
        yield [3299, 6];
        yield [3300, 7];
        yield [99999, 7];
    }

    #[DataProvider('thresholds')]
    public function testOf(int $xp, int $number): void
    {
        $level = Level::of($xp);

        self::assertSame($number, $level->number);
        self::assertSame('level.'.$number, $level->name);
        self::assertSame($xp, $level->xp);
        self::assertSame(Level::LEVELS[$number], $level->from);
    }

    public function testNextLevel(): void
    {
        $level = Level::of(200);

        self::assertSame(400, $level->to);
        self::assertSame('level.3', $level->next);
    }

    public function testTopLevel(): void
    {
        $level = Level::of(5000);

        self::assertNull($level->to);
        self::assertNull($level->next);
        self::assertSame(100, $level->percent());
    }

    public function testPercent(): void
    {
        self::assertSame(0, Level::of(0)->percent());
        self::assertSame(50, Level::of(75)->percent());
        self::assertSame(99, Level::of(149)->percent());
        self::assertSame(0, Level::of(150)->percent());
        self::assertSame(20, Level::of(200)->percent());
    }

    public function testToArray(): void
    {
        self::assertSame(['number' => 2, 'name' => 'level.2', 'xp' => 275, 'from' => 150, 'to' => 400, 'pct' => 50, 'next' => 'level.3'], Level::of(275)->toArray());
    }
}
