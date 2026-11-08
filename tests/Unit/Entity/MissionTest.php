<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Mission;
use App\Enum\CallEnd;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A mission: who it concerns, its bounds, the calls that count towards it, and whether it is overdue.
 */
final class MissionTest extends TestCase
{
    use Fixtures;

    private static function mission(?Member $assignee = null, ?string $situation = null, int $calls = 3, int $target = 60, ?string $due = '2026-11-20'): Mission
    {
        return new Mission($assignee, $situation, $calls, $target, null !== $due ? new \DateTimeImmutable($due, new \DateTimeZone('UTC')) : null, 'Train', null, self::at('2026-11-10 09:00:00'));
    }

    private static function surpriseCall(string $key, int $score, string $at = '2026-11-12 10:00:00'): Call
    {
        $scenario = self::scenario();
        $scenario->setSituation(self::situation($key));

        return self::doneCall($score, scenario: $scenario, at: $at);
    }

    public function testBoundsAreClamped(): void
    {
        $low = self::mission(calls: 0, target: -10);
        $high = self::mission(calls: 50, target: 150);

        self::assertSame([1, 0], [$low->calls, $low->target]);
        self::assertSame([20, 100], [$high->calls, $high->target]);
    }

    public function testConcerns(): void
    {
        $alice = self::member();
        $bob = self::member('Bob', email: 'bob@example.com');

        self::assertTrue(self::mission()->concerns($alice));
        self::assertTrue(self::mission()->concerns($bob));
        self::assertTrue(self::mission($alice)->concerns($alice));
        self::assertFalse(self::mission($alice)->concerns($bob));
    }

    public function testProgressCountsOnlyDoneCallsAtOrAboveTheTarget(): void
    {
        $live = self::call(at: '2026-11-12 10:00:00');
        $debriefing = self::call(at: '2026-11-12 10:00:00');
        $debriefing->stop(CallEnd::Seller, self::at('2026-11-12 10:05:00'));

        $calls = [
            self::doneCall(60, at: '2026-11-12 10:00:00'),
            self::doneCall(85, at: '2026-11-13 10:00:00'),
            self::doneCall(59, at: '2026-11-13 11:00:00'),
            $live,
            $debriefing,
        ];

        self::assertSame(2, self::mission()->progress($calls));
    }

    public function testProgressIgnoresCallsOutsideTheMissionsDates(): void
    {
        $calls = [
            self::doneCall(90, at: '2026-11-09 10:00:00'),
            self::doneCall(90, at: '2026-11-20 23:30:00'),
            self::doneCall(90, at: '2026-11-21 08:00:00'),
        ];

        self::assertSame(1, self::mission()->progress($calls));
        self::assertSame(2, self::mission(due: null)->progress($calls));
    }

    public function testProgressIgnoresReplays(): void
    {
        $original = self::doneCall(90, at: '2026-11-12 10:00:00');
        $replay = Call::replay($original, 1, self::at('2026-11-12 11:00:00'));
        $replay->stop(CallEnd::Seller, self::at('2026-11-12 11:05:00'));
        $replay->close([], 95);

        self::assertSame(1, self::mission()->progress([$original, $replay]));
    }

    public function testProgressCountsOnlyTheMissionsSituation(): void
    {
        $calls = [self::surpriseCall('price_request', 80), self::surpriseCall('cold_call', 80), self::doneCall(80, at: '2026-11-12 10:00:00')];

        self::assertSame(1, self::mission(situation: 'price_request')->progress($calls));
        self::assertSame(3, self::mission()->progress($calls));
    }

    public function testProgressStopsAtTheNumberOfCalls(): void
    {
        $calls = array_map(static fn (int $d): Call => self::doneCall(90, at: "2026-11-1{$d} 10:00:00"), [1, 2, 3, 4, 5]);

        self::assertSame(3, self::mission(calls: 3)->progress($calls));
    }

    public function testIsOverdue(): void
    {
        $mission = self::mission(due: '2026-11-20');

        self::assertFalse($mission->isOverdue(self::at('2026-11-20 23:59:59')));
        self::assertTrue($mission->isOverdue(self::at('2026-11-21 00:00:00')));
        self::assertFalse(self::mission(due: null)->isOverdue(self::at('2099-01-01 00:00:00')));
    }
}
