<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progress;

use App\Entity\Call;
use App\Enum\CallEnd;
use App\Progress\Experience;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Experience points of a call (score plus objective bonuses, none for replays or unfinished calls) and streaks of
 * consecutive days.
 */
final class ExperienceTest extends TestCase
{
    use Fixtures;

    private const array DEBRIEF = ['objectives' => [
        ['objective' => 'A', 'status' => 'reached', 'evidence' => ''],
        ['objective' => 'B', 'status' => 'partial', 'evidence' => ''],
        ['objective' => 'C', 'status' => 'missed', 'evidence' => ''],
    ]];

    public function testOfADoneCall(): void
    {
        self::assertSame(70 + 10 + 4, new Experience()->of(self::doneCall(70, self::DEBRIEF)));
        self::assertSame(55, new Experience()->of(self::doneCall(55, ['summary' => 'No objectives'])));
    }

    public function testOfAnUnfinishedCall(): void
    {
        $live = self::call();
        $debriefing = self::call();
        $debriefing->stop(CallEnd::Seller, self::at());

        self::assertSame(0, new Experience()->of($live));
        self::assertSame(0, new Experience()->of($debriefing));
    }

    public function testOfAReplay(): void
    {
        $replay = Call::replay(self::doneCall(80), 1, self::at('2026-11-14 10:00:00'));
        $replay->stop(CallEnd::Seller, self::at('2026-11-14 10:05:00'));
        $replay->close(self::DEBRIEF, 90);

        self::assertSame(0, new Experience()->of($replay));
    }

    public function testStreakUpToToday(): void
    {
        $calls = [
            self::doneCall(50, at: '2026-11-13 09:00:00'),
            self::doneCall(50, at: '2026-11-12 18:00:00'),
            self::doneCall(50, at: '2026-11-12 09:00:00'),
            self::doneCall(50, at: '2026-11-11 09:00:00'),
            self::doneCall(50, at: '2026-11-09 09:00:00'),
        ];

        self::assertSame(3, new Experience()->streak($calls, self::at('2026-11-13 20:00:00')));
    }

    public function testStreakUpToYesterdayWhenTodayIsStillToBePlayed(): void
    {
        $calls = [self::doneCall(50, at: '2026-11-12 09:00:00'), self::doneCall(50, at: '2026-11-11 09:00:00')];

        self::assertSame(2, new Experience()->streak($calls, self::at('2026-11-13 08:00:00')));
        self::assertSame(0, new Experience()->streak($calls, self::at('2026-11-14 08:00:00')));
    }

    public function testStreakCountsOnlyDoneCalls(): void
    {
        $abandoned = self::call(at: '2026-11-12 09:00:00');
        $abandoned->stop(CallEnd::Seller, self::at('2026-11-12 09:01:00'), abandoned: true);

        self::assertSame(1, new Experience()->streak([self::doneCall(50, at: '2026-11-13 09:00:00'), $abandoned], self::at('2026-11-13 20:00:00')));
        self::assertSame(0, new Experience()->streak([], self::at()));
    }
}
