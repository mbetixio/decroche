<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Coach;

use App\Call\Coach\Debrief;
use App\Call\Speech\SpeechFigures;
use App\Enum\BriefSection;
use App\Enum\ObjectiveStatus;
use PHPUnit\Framework\TestCase;

/**
 * The debrief's array form, as stored on the call: its round trip, and reading loose or partial data.
 */
final class DebriefTest extends TestCase
{
    private static function debrief(): Debrief
    {
        return new Debrief(
            criteria: ['discovery' => ['label' => 'Discovery', 'score' => 7, 'comment' => 'Good questions.']],
            summary: 'A solid call.',
            nextStep: true,
            outcome: 'Meeting booked.',
            result: 'Second meeting on Tuesday',
            objectives: [
                ['objective' => 'Understand the need', 'status' => ObjectiveStatus::Reached, 'evidence' => 'Asked about hiring.'],
                ['objective' => 'Get a meeting', 'status' => ObjectiveStatus::Partial, 'evidence' => ''],
            ],
            goal: ObjectiveStatus::Partial,
            missed: [['section' => BriefSection::Budget, 'what' => '50k', 'how' => 'What budget did you plan?']],
            best: [['quote' => 'What matters most to you?', 'why' => 'Open.']],
            worst: [['quote' => 'We are the best.', 'why' => 'Empty claim.', 'better' => 'Here is a reference.']],
            tips: ['Ask about the budget.', 'Book a date.'],
            speech: new SpeechFigures(true, 55, 110, 90, 6, 4, 3, ['um' => 3], 25, 140, 2),
            speechComment: 'Good pace.',
        );
    }

    public function testTheWeakestCriterionIsTheLowestUnderSeven(): void
    {
        $grade = static fn (int $score): array => ['label' => 'A criterion', 'score' => $score, 'comment' => ''];
        $with = static fn (array $criteria): Debrief => Debrief::fromArray(['criteria' => $criteria] + self::debrief()->toArray());

        self::assertSame('closing', $with(['discovery' => $grade(6), 'closing' => $grade(3), 'value' => $grade(3)])->weakest(), 'The first one on a tie.');
        self::assertSame('discovery', $with(['discovery' => $grade(6), 'closing' => $grade(8)])->weakest());
        self::assertNull($with(['discovery' => $grade(7), 'closing' => $grade(9)])->weakest(), 'Nothing to train when all are good.');
    }

    public function testToArray(): void
    {
        $data = self::debrief()->toArray();

        self::assertSame('reached', $data['objectives'][0]['status']);
        self::assertSame('partial', $data['goal']);
        self::assertSame(['section' => 'budget', 'what' => '50k', 'how' => 'What budget did you plan?'], $data['missed'][0]);
        self::assertTrue($data['next_step']);
        self::assertSame(140, $data['speech']['wpm']);
        self::assertSame('Good pace.', $data['speech_comment']);
    }

    public function testRoundTrip(): void
    {
        $debrief = self::debrief();

        self::assertEquals($debrief, Debrief::fromArray($debrief->toArray()));
        self::assertEquals($debrief, Debrief::fromArray(json_decode(json_encode($debrief->toArray(), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR)));
    }

    public function testFromEmptyArray(): void
    {
        $debrief = Debrief::fromArray([]);

        self::assertSame([], $debrief->criteria);
        self::assertSame('', $debrief->summary);
        self::assertFalse($debrief->nextStep);
        self::assertSame([], $debrief->objectives);
        self::assertNull($debrief->goal);
        self::assertSame([], $debrief->missed);
        self::assertSame([], $debrief->tips);
        self::assertNull($debrief->speech);
        self::assertSame('', $debrief->speechComment);
    }

    public function testFromLooseArray(): void
    {
        $debrief = Debrief::fromArray([
            'criteria' => 'not a list',
            'objectives' => [3 => ['objective' => 'A', 'status' => 'atteint']],
            'goal' => 'bogus',
            'missed' => [['section' => 'budget'], ['section' => 'unknown', 'what' => 'x']],
            'tips' => ['b' => 'Tip'],
        ]);

        self::assertSame([], $debrief->criteria);
        self::assertSame([['objective' => 'A', 'status' => ObjectiveStatus::Reached, 'evidence' => '']], $debrief->objectives);
        self::assertNull($debrief->goal);
        self::assertSame([['section' => BriefSection::Budget, 'what' => '', 'how' => '']], $debrief->missed);
        self::assertSame(['Tip'], $debrief->tips);
    }
}
