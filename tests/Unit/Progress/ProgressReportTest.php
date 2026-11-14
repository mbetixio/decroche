<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progress;

use App\Entity\Call;
use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Enum\Difficulty;
use App\Enum\Language;
use App\Progress\ProgressReport;
use App\Repository\CallRepository;
use App\Tests\Unit\Fixtures;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * A member's figures summarized from their calls (averages, trend, criteria, the difficulty of the next calls, goals, discovery) and the
 * figures by situation. The repositories are final: they are real ones over stubbed Doctrine plumbing.
 */
final class ProgressReportTest extends TestCase
{
    use Fixtures;

    private static function report(Language $language = Language::English): ProgressReport
    {
        return new ProgressReport(
            new CallRepository(self::createStub(ManagerRegistry::class)),
            self::companies(self::company($language)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function debrief(int $discovery, int $closing, string $goal = 'missed'): array
    {
        return [
            'criteria' => [
                'discovery' => ['label' => 'Discovery', 'score' => $discovery, 'comment' => ''],
                'closing' => ['label' => 'Closing', 'score' => $closing, 'comment' => ''],
            ],
            'objectives' => [['objective' => 'Get a meeting', 'status' => $goal, 'evidence' => '']],
            'goal' => $goal,
        ];
    }

    private static function surprise(string $key): Scenario
    {
        $scenario = self::scenario();
        $scenario->setSituation(self::situation($key));

        return $scenario;
    }

    public function testSummarizeNothing(): void
    {
        $stats = self::report(Language::French)->summarize([]);

        self::assertSame(0, $stats->calls);
        self::assertSame(0, $stats->done);
        self::assertNull($stats->average);
        self::assertNull($stats->best);
        self::assertNull($stats->last);
        self::assertNull($stats->trend);
        self::assertNull($stats->weakest);
        self::assertNull($stats->discovery);
        self::assertNull($stats->goalRate());
        self::assertSame(Difficulty::Approachable, $stats->challenge->difficulty);
        self::assertTrue($stats->challenge->isWarmingUp());
        self::assertSame([], $stats->bySituation);
        // Without any debrief, the criteria are the company's default scorecard, in its language.
        self::assertSame(['label' => 'Découverte du besoin', 'average' => null], $stats->criteria['discovery']);
    }

    public function testSummarize(): void
    {
        $abandoned = self::call(at: '2026-11-13 12:00:00');
        $abandoned->stop(CallEnd::Seller, self::at('2026-11-13 12:01:00'), abandoned: true);
        $calls = [
            $abandoned,
            self::doneCall(80, self::debrief(8, 4, 'reached'), at: '2026-11-13 11:00:00'),
            self::doneCall(60, self::debrief(6, 2), at: '2026-11-12 11:00:00'),
            self::doneCall(70, self::debrief(7, 6, 'partial'), at: '2026-11-11 11:00:00'),
        ];
        $stats = self::report()->summarize($calls);

        self::assertSame(4, $stats->calls);
        self::assertSame(3, $stats->done);
        self::assertSame(1, $stats->abandoned);
        self::assertSame(70, $stats->average);
        self::assertSame(80, $stats->best);
        self::assertSame(80, $stats->last);
        self::assertNull($stats->trend);
        self::assertSame(['discovery' => ['label' => 'Discovery', 'average' => 7.0], 'closing' => ['label' => 'Closing', 'average' => 4.0]], $stats->criteria);
        self::assertSame('closing', $stats->weakest);
        self::assertSame('discovery', $stats->strongest);
        self::assertSame(1, $stats->goals);
        self::assertSame(33, $stats->goalRate());
        // Three graded calls averaging 70: the next ones are tough.
        self::assertSame(Difficulty::Tough, $stats->challenge->difficulty);
        self::assertSame(70, $stats->challenge->recentAverage);
        self::assertSame([70, 60, 80], array_column($stats->scores, 'score'));
        self::assertSame(['2026-11-11', '2026-11-12', '2026-11-13'], array_column($stats->scores, 'date'));
    }

    public function testSummarizeIgnoresReplaysAndUnscoredCalls(): void
    {
        $original = self::doneCall(50, at: '2026-11-12 10:00:00');
        $replay = Call::replay($original, 1, self::at('2026-11-13 10:00:00'));
        $replay->stop(CallEnd::Seller, self::at('2026-11-13 10:05:00'));
        $replay->close([], 100);
        $debriefing = self::call(at: '2026-11-13 11:00:00');
        $debriefing->stop(CallEnd::Seller, self::at('2026-11-13 11:05:00'));

        $stats = self::report()->summarize([$debriefing, $replay, $original]);

        self::assertSame(3, $stats->calls);
        self::assertSame(1, $stats->done);
        self::assertSame(50, $stats->average);
        self::assertSame(1, $stats->challenge->basis, 'The replay does not count.');
    }

    public function testTrendComparesTheLastThreeScoresWithTheThreeBefore(): void
    {
        $scores = [90, 80, 70, 60, 50, 40, 10];
        $calls = array_map(static fn (int $i): Call => self::doneCall($scores[$i], at: \sprintf('2026-11-%02d 10:00:00', 13 - $i)), array_keys($scores));

        self::assertSame(30, self::report()->summarize($calls)->trend);
        // (90 + 80 + 70) / 3 against the single score before: 80 - 60.
        self::assertSame(20, self::report()->summarize(\array_slice($calls, 0, 4))->trend);
        self::assertNull(self::report()->summarize(\array_slice($calls, 0, 3))->trend);
    }

    public function testDiscoveryIsTheShareOfBriefSectionsFound(): void
    {
        // The fixture scenario has two brief sections (pain and budget).
        $one = self::doneCall(50);
        $one->reveal(BriefSection::Pain);
        $two = self::doneCall(50);

        self::assertSame(25, self::report()->summarize([$one, $two])->discovery);
    }

    public function testBySituation(): void
    {
        $done = [
            self::doneCall(80, self::debrief(5, 5, 'reached'), self::surprise('cold_call')),
            self::doneCall(61, self::debrief(5, 5), self::surprise('price_request')),
            self::doneCall(70, self::debrief(5, 5, 'reached'), self::surprise('price_request')),
            self::doneCall(90, self::debrief(5, 5, 'reached')),
        ];
        $rows = self::report()->bySituation($done);

        self::assertSame([
            ['key' => 'price_request', 'label' => 'Price request on the website', 'emoji' => '💶', 'calls' => 2, 'average' => 66, 'goals' => 50],
            ['key' => 'cold_call', 'label' => 'Price request on the website', 'emoji' => '💶', 'calls' => 1, 'average' => 80, 'goals' => 100],
        ], $rows);
    }

    public function testStatsToArray(): void
    {
        $data = self::report()->summarize([self::doneCall(80, self::debrief(8, 4, 'reached'))])->toArray();

        self::assertSame(80, $data['average']);
        self::assertSame(100, $data['goal_rate']);
        self::assertSame(1, $data['goals_reached']);
        self::assertSame(['difficulty' => 'Approachable', 'recent_average' => null, 'basis' => 1], $data['challenge']);
    }
}
