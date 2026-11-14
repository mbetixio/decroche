<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Call;
use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Enum\CallStatus;
use App\Enum\Speaker;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A practice call: its lines (the persona opens), the brief sections revealed, its clock, its life (live, stopped,
 * closed), replays of a moment, finding a quoted line, and the brief sheet of the debrief.
 */
final class CallTest extends TestCase
{
    use Fixtures;

    public function testTheConstructorSaysTheOpeningLine(): void
    {
        $call = self::call();
        $lines = $call->lines();

        self::assertCount(1, $lines);
        self::assertSame(Speaker::Persona, $lines[0]->speaker);
        self::assertSame('Hello, Claire speaking.', $lines[0]->text);
        self::assertSame(self::at()->getTimestamp(), $lines[0]->at->getTimestamp());
        self::assertSame(self::at()->getTimestamp(), $call->startedAt->getTimestamp());
        self::assertSame(CallStatus::Live, $call->status);
        self::assertTrue($call->isLive());
        self::assertFalse($call->isDone());
        self::assertFalse($call->isReplay());
    }

    public function testSayAddsLines(): void
    {
        $call = self::call();
        $call->say(Speaker::Seller, 'Hi Claire', self::at('2026-11-13 10:00:20'));
        $call->say(Speaker::Persona, 'Yes?', self::at('2026-11-13 10:00:25'));

        self::assertSame(['Hello, Claire speaking.', 'Hi Claire', 'Yes?'], array_map(static fn ($l): string => $l->text, $call->lines()));
        self::assertSame(1, $call->sellerTurns());
        self::assertFalse($call->isSpoken());
    }

    public function testVoiceDurationIsClampedAndMakesTheCallSpoken(): void
    {
        $call = self::call();
        $call->say(Speaker::Seller, 'One', self::at(), -50);
        $call->say(Speaker::Seller, 'Two', self::at(), 9_000_000);
        $lines = $call->lines();

        self::assertSame(0, $lines[1]->voiceMs);
        self::assertSame(600_000, $lines[2]->voiceMs);
        self::assertTrue($call->isSpoken());
    }

    public function testSellerTurns(): void
    {
        $call = self::call();
        self::assertSame(0, $call->sellerTurns());
        $call->say(Speaker::Seller, 'a', self::at());
        $call->say(Speaker::Persona, 'b', self::at());
        $call->say(Speaker::Seller, 'c', self::at());

        self::assertSame(2, $call->sellerTurns());
    }

    public function testRevealOnce(): void
    {
        $call = self::call();

        self::assertSame(0, $call->revealedCount());
        self::assertTrue($call->reveal(BriefSection::Budget));
        self::assertFalse($call->reveal(BriefSection::Budget));
        self::assertTrue($call->reveal(BriefSection::Pain));
        self::assertSame(2, $call->revealedCount());
        self::assertTrue($call->isRevealed(BriefSection::Budget));
        self::assertFalse($call->isRevealed(BriefSection::Secret));
    }

    public function testSecondsLeftCountDownToTheDeadline(): void
    {
        $call = self::call(self::scenario(minutes: 10));

        self::assertSame(600, $call->secondsLeft(self::at()));
        self::assertSame(420, $call->secondsLeft(self::at('2026-11-13 10:03:00')));
        self::assertSame(1, $call->secondsLeft(self::at('2026-11-13 10:09:59')));
        self::assertSame(0, $call->secondsLeft(self::at('2026-11-13 10:15:00')));
    }

    public function testStopWaitsForTheDebrief(): void
    {
        $call = self::call();
        $call->stop(CallEnd::Persona, self::at('2026-11-13 10:04:10'));

        self::assertSame(CallStatus::Debriefing, $call->status);
        self::assertSame(CallEnd::Persona, $call->endedBy);
        self::assertSame(self::at('2026-11-13 10:04:10')->getTimestamp(), $call->endedAt?->getTimestamp());
        self::assertFalse($call->isLive());
        self::assertFalse($call->isDone());
        self::assertSame(5, $call->durationMinutes(self::at('2026-11-13 11:00:00')));
    }

    public function testStopAbandoned(): void
    {
        $call = self::call();
        $call->stop(CallEnd::Seller, self::at(), abandoned: true);

        self::assertSame(CallStatus::Abandoned, $call->status);
        self::assertFalse($call->isLive());
    }

    public function testDurationOfALiveCall(): void
    {
        self::assertSame(3, self::call()->durationMinutes(self::at('2026-11-13 10:02:01')));
    }

    public function testCloseRecordsTheDebriefAndClampsTheScore(): void
    {
        $call = self::call();
        $call->stop(CallEnd::Seller, self::at());
        $call->close(['summary' => 'Good'], 140);

        self::assertTrue($call->isDone());
        self::assertSame(CallStatus::Done, $call->status);
        self::assertSame(100, $call->score);
        self::assertSame(['summary' => 'Good'], $call->debrief);

        $call->close([], -5);
        self::assertSame(0, $call->score);
    }

    private static function conversation(): Call
    {
        $call = self::call();
        $call->say(Speaker::Seller, 'Hello Claire, I’m calling about your request.', self::at('2026-11-13 10:00:30'));
        $call->say(Speaker::Persona, 'Yes, go on.', self::at('2026-11-13 10:00:40'), 900);
        $call->say(Speaker::Seller, 'What would make this project a success for you?', self::at('2026-11-13 10:02:00'), 2000);
        $call->say(Speaker::Persona, 'Shipping on time.', self::at('2026-11-13 10:02:10'));

        return $call;
    }

    public function testReplayKeepsTheLinesBeforeTheMoment(): void
    {
        $from = self::conversation();
        $now = self::at('2026-11-14 09:00:00');
        $replay = Call::replay($from, 3, $now);
        $lines = $replay->lines();

        self::assertCount(3, $lines);
        self::assertSame(['Hello, Claire speaking.', 'Hello Claire, I’m calling about your request.', 'Yes, go on.'], array_map(static fn ($l): string => $l->text, $lines));
        self::assertSame(900, $lines[2]->voiceMs);
        self::assertTrue($replay->isReplay());
        self::assertSame($from, $replay->replayOf);
        self::assertTrue($replay->isLive());
        self::assertSame($from->member, $replay->member);
        self::assertSame($from->scenario, $replay->scenario);
    }

    public function testReplayResumesWithTheTimeThatWasLeft(): void
    {
        $from = self::conversation();
        $now = self::at('2026-11-14 09:00:00');
        $replay = Call::replay($from, 3, $now);

        // The replayed line was said 2 minutes in: the replay "started" 2 minutes ago, 8 minutes are left.
        self::assertSame($now->getTimestamp() - 120, $replay->startedAt->getTimestamp());
        self::assertSame(480, $replay->secondsLeft($now));
        // Kept lines are shifted so that the moment replayed is now.
        self::assertSame($now->getTimestamp() - 80, $replay->lines()[2]->at->getTimestamp());
        // The original call is left untouched.
        self::assertCount(5, $from->lines());
        self::assertFalse($from->isReplay());
    }

    public function testReplayOfAReplayPointsToTheOriginal(): void
    {
        $from = self::conversation();
        $replay = Call::replay($from, 3, self::at('2026-11-14 09:00:00'));
        $replay->say(Speaker::Seller, 'Another question?', self::at('2026-11-14 09:00:10'));
        $again = Call::replay($replay, 3, self::at('2026-11-15 09:00:00'));

        self::assertSame($from, $again->replayOf);
    }

    public function testReplayRejectsAPersonaLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('call.error.replay_line');

        Call::replay(self::conversation(), 2, self::at());
    }

    public function testReplayRejectsTheOpeningAndAMissingLine(): void
    {
        $from = self::conversation();
        foreach ([0, 5, -1] as $n) {
            try {
                Call::replay($from, $n, self::at());
                self::fail("Line {$n} should not be replayable.");
            } catch (\InvalidArgumentException $e) {
                self::assertSame('call.error.replay_line', $e->getMessage());
            }
        }
    }

    public function testLineOfFindsTheSellerLineOfAQuote(): void
    {
        $call = self::conversation();

        self::assertSame(1, $call->lineOf("I'm calling about your request"));
        self::assertSame(3, $call->lineOf('  WHAT would make this project a success  '));
        self::assertSame(3, $call->lineOf('success for you'));
    }

    public function testLineOfIgnoresPersonaLinesAndEmptyQuotes(): void
    {
        $call = self::conversation();

        self::assertNull($call->lineOf('Shipping on time'));
        self::assertNull($call->lineOf('never said'));
        self::assertNull($call->lineOf(' ?! '));
    }

    public function testBriefSheetListsTheWrittenSectionsInOrder(): void
    {
        $call = self::call(self::scenario(brief: ['pain' => 'Lead dev leaving', 'budget' => '50k', 'secret' => '  ', 'setup' => 'At the office']));
        $call->reveal(BriefSection::Pain);

        self::assertSame([
            ['section' => BriefSection::Budget, 'text' => '50k', 'found' => false],
            ['section' => BriefSection::Pain, 'text' => 'Lead dev leaving', 'found' => true],
        ], $call->briefSheet());
    }
}
