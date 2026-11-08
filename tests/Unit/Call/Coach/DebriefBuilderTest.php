<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Coach;

use App\Call\Coach\DebriefBuilder;
use App\Call\Speech\SpeechFigures;
use App\Entity\Call;
use App\Enum\BriefSection;
use App\Enum\Language;
use App\Enum\ObjectiveStatus;
use App\Enum\Speaker;
use App\Model\Criterion;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Turning the model's JSON into a reliable debrief and score: grades clamped, objectives mapped in order, the score
 * computed (70 % skills, 30 % objectives), sections discovered or missed, only quotes really said; and the empty
 * debrief of a silent call.
 */
final class DebriefBuilderTest extends TestCase
{
    use Fixtures;

    /**
     * @return array<string, Criterion>
     */
    private static function scorecard(): array
    {
        return ['discovery' => new Criterion('discovery', 'Discovery'), 'closing' => new Criterion('closing', 'Closing')];
    }

    /**
     * @param list<string> $objectives
     */
    private static function conversation(array $objectives = ['Understand the need', 'Find the budget', 'Get a meeting']): Call
    {
        $scenario = self::scenario(brief: ['pain' => 'Lead dev leaving', 'budget' => '50k', 'decision' => 'The CEO']);
        $scenario->setObjectives($objectives);
        $call = self::call($scenario);
        $call->say(Speaker::Seller, 'What would make this project a success for you?', self::at()->modify('+10 seconds'));
        $call->say(Speaker::Persona, 'Shipping on time.', self::at()->modify('+20 seconds'));
        $call->say(Speaker::Seller, 'We are simply the best team in Europe, trust me.', self::at()->modify('+30 seconds'));

        return $call;
    }

    private static function figures(): SpeechFigures
    {
        return new SpeechFigures(false, 60, 20, 10, 1, 1, 0, [], 11, null, 3);
    }

    public function testGradesAreClampedAndTheScoreComputed(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(), [
            'criteria' => ['discovery' => ['score' => 12, 'comment' => ' Great. '], 'closing' => ['score' => '7.6']],
            'objectives' => [['status' => 'reached', 'evidence' => 'Asked'], ['status' => 'partially']],
        ], self::scorecard(), self::figures());
        $debrief = $result['debrief'];

        self::assertSame(['label' => 'Discovery', 'score' => 10, 'comment' => 'Great.'], $debrief->criteria['discovery']);
        self::assertSame(['label' => 'Closing', 'score' => 8, 'comment' => ''], $debrief->criteria['closing']);
        // Skills 90; objectives (1 + 0.5 + 0) / 3 = 0.5: 0.7 × 90 + 30 × 0.5 = 78.
        self::assertSame(78, $result['score']);
        self::assertSame(ObjectiveStatus::Partial, $debrief->goal);
    }

    public function testNegativeAndMissingGrades(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation([]), [
            'criteria' => ['discovery' => ['score' => -4], 'unknown' => ['score' => 10]],
        ], self::scorecard(), self::figures());

        self::assertSame(0, $result['debrief']->criteria['discovery']['score']);
        self::assertSame(0, $result['debrief']->criteria['closing']['score']);
        self::assertArrayNotHasKey('unknown', $result['debrief']->criteria);
        self::assertSame(0, $result['score']);
        self::assertNull($result['debrief']->goal);
    }

    public function testWithoutObjectivesTheScoreIsTheSkills(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation([]), [
            'criteria' => ['discovery' => ['score' => 7], 'closing' => ['score' => 6]],
            'objectives' => [['status' => 'reached']],
        ], self::scorecard(), self::figures());

        self::assertSame(65, $result['score']);
        self::assertSame([], $result['debrief']->objectives);
        self::assertNull($result['debrief']->goal);
    }

    public function testObjectivesAreMappedInOrder(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(), [
            'criteria' => ['discovery' => ['score' => 10], 'closing' => ['score' => 10]],
            'objectives' => ['x' => ['status' => 'reached', 'evidence' => 'one'], 'y' => 'garbage', 'z' => ['status' => 'atteint', 'evidence' => 3]],
        ], self::scorecard(), self::figures());
        $objectives = $result['debrief']->objectives;

        self::assertSame([
            ['objective' => 'Understand the need', 'status' => ObjectiveStatus::Reached, 'evidence' => 'one'],
            ['objective' => 'Find the budget', 'status' => ObjectiveStatus::Missed, 'evidence' => ''],
            ['objective' => 'Get a meeting', 'status' => ObjectiveStatus::Reached, 'evidence' => '3'],
        ], $objectives);
        self::assertSame(90, $result['score']);
    }

    public function testGoalReachedWhenEveryObjectiveIs(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(['One']), [
            'objectives' => [['status' => 'reached']],
        ], self::scorecard(), self::figures());

        self::assertSame(ObjectiveStatus::Reached, $result['debrief']->goal);
        self::assertSame(30, $result['score']);

        $missed = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(['One']), [], self::scorecard(), self::figures());
        self::assertSame(ObjectiveStatus::Missed, $missed['debrief']->goal);
    }

    public function testDiscoveredAndMissedSections(): void
    {
        $call = self::conversation();
        $call->reveal(BriefSection::Decision);
        $result = new DebriefBuilder(new IdentityTranslator())->build($call, [
            'discovered' => [' budget ', 'secret', 'nonsense', 42],
            'missed' => [
                ['rubric' => 'pain', 'what' => 'Lead dev leaving', 'how' => 'Who maintains the code?'],
                ['rubric' => 'budget', 'what' => 'found anyway'],
                ['rubric' => 'decision', 'what' => 'revealed during the call'],
                ['rubric' => 'pain', 'what' => 'twice', 'how' => 'again'],
                'garbage',
            ],
        ], self::scorecard(), self::figures());

        self::assertSame([BriefSection::Budget], $result['discovered']);
        self::assertSame([['section' => BriefSection::Pain, 'what' => 'twice', 'how' => 'again']], $result['debrief']->missed);
    }

    public function testOnlyQuotesReallySaidAreKept(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(), [
            'best' => [
                ['quote' => 'what would make this project a success', 'why' => 'Open question.'],
                ['quote' => 'Shipping on time.', 'why' => 'The persona said it, not the seller.'],
                ['quote' => 'A line the seller never said at all.', 'why' => 'Invented.'],
                ['quote' => 'ok', 'why' => 'Too short to check.'],
            ],
            'worst' => [
                ['quote' => 'We are simply the best team in Europe, trust me!', 'why' => 'Empty claim.', 'better' => 'Give a reference.'],
                ['quote' => 'We are simply the best team in Europe trust me', 'why' => 'Again.'],
                ['quote' => 'We are simply the best team in Europe', 'why' => 'Thrice.'],
            ],
        ], self::scorecard(), self::figures());
        $debrief = $result['debrief'];

        self::assertSame([['quote' => 'what would make this project a success', 'why' => 'Open question.']], $debrief->best);
        self::assertCount(2, $debrief->worst);
        self::assertSame(['quote' => 'We are simply the best team in Europe, trust me!', 'why' => 'Empty claim.', 'better' => 'Give a reference.'], $debrief->worst[0]);
        self::assertSame('', $debrief->worst[1]['better']);
    }

    public function testTextsAndTips(): void
    {
        $result = new DebriefBuilder(new IdentityTranslator())->build(self::conversation(), [
            'summary' => '  Solid. ', 'next_step' => 1, 'outcome' => ['nope'], 'result' => 'Meeting booked', 'speech' => 'Talks too much.',
            'tips' => ['One', '', 'Two', ['x'], 'Three', 'Four'],
        ], self::scorecard(), $figures = self::figures());
        $debrief = $result['debrief'];

        self::assertSame('Solid.', $debrief->summary);
        self::assertTrue($debrief->nextStep);
        self::assertSame('', $debrief->outcome);
        self::assertSame('Meeting booked', $debrief->result);
        self::assertSame('Talks too much.', $debrief->speechComment);
        self::assertSame(['One', 'Two', 'Three'], $debrief->tips);
        self::assertSame($figures, $debrief->speech);
    }

    public function testEmpty(): void
    {
        $debrief = new DebriefBuilder(new IdentityTranslator())->empty(self::conversation(['One', 'Two']), self::scorecard(), self::figures(), Language::English);

        self::assertSame(['discovery' => ['label' => 'Discovery', 'score' => 0, 'comment' => 'debrief.empty.comment'], 'closing' => ['label' => 'Closing', 'score' => 0, 'comment' => 'debrief.empty.comment']], $debrief->criteria);
        self::assertSame('debrief.empty.summary', $debrief->summary);
        self::assertSame('debrief.empty.outcome', $debrief->outcome);
        self::assertSame('debrief.empty.result', $debrief->result);
        self::assertSame(['debrief.empty.tip'], $debrief->tips);
        self::assertFalse($debrief->nextStep);
        self::assertSame([['objective' => 'One', 'status' => ObjectiveStatus::Missed, 'evidence' => ''], ['objective' => 'Two', 'status' => ObjectiveStatus::Missed, 'evidence' => '']], $debrief->objectives);
        self::assertSame(ObjectiveStatus::Missed, $debrief->goal);
        self::assertSame([], $debrief->best);
    }

    public function testEmptyWithoutObjectives(): void
    {
        $debrief = new DebriefBuilder(new IdentityTranslator())->empty(self::conversation([]), self::scorecard(), self::figures(), Language::French);

        self::assertNull($debrief->goal);
        self::assertSame([], $debrief->objectives);
    }
}
