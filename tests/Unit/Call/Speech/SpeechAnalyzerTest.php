<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Speech;

use App\Call\Speech\SpeechAnalyzer;
use App\Entity\Call;
use App\Enum\Language;
use App\Enum\Speaker;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The call in figures, in French and English: talk share, questions (open or not), filler words, longest line,
 * speech rate and reply delay.
 */
final class SpeechAnalyzerTest extends TestCase
{
    use Fixtures;

    /**
     * @param list<array{Speaker, string, int, ?int}> $lines speaker, text, seconds after the start, voice ms
     */
    private static function callWith(array $lines): Call
    {
        // The fixture's opening line is "Hello, Claire speaking." (3 words), at 0 s.
        $call = self::call();
        foreach ($lines as [$speaker, $text, $seconds, $ms]) {
            $call->say($speaker, $text, self::at()->modify("+{$seconds} seconds"), $ms);
        }

        return $call;
    }

    public function testEnglish(): void
    {
        $call = self::callWith([
            [Speaker::Seller, 'Um, hi Claire. What is your main challenge right now?', 6, null],
            [Speaker::Persona, 'Hiring, mostly.', 10, null],
            [Speaker::Seller, 'I see, you know, we basically place developers fast. Is that useful?', 20, null],
        ]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::English);

        self::assertFalse($figures->voice);
        self::assertSame(22, $figures->sellerWords);
        self::assertSame(5, $figures->personaWords);
        self::assertSame(81, $figures->talkShare);
        self::assertSame(2, $figures->questions);
        self::assertSame(1, $figures->openQuestions);
        self::assertSame(4, $figures->fillers);
        self::assertSame(['um' => 1, 'right' => 1, 'you know' => 1, 'basically' => 1], $figures->fillerWords);
        self::assertSame(12, $figures->longest);
        self::assertNull($figures->wordsPerMinute);
        // 6 s after the opening, 10 s after "Hiring, mostly.": 8 s on average.
        self::assertSame(8, $figures->replyDelay);
    }

    public function testFrench(): void
    {
        $call = self::callWith([
            [Speaker::Seller, 'Euh bonjour, du coup comment gérez-vous vos recrutements', 4, null],
            [Speaker::Persona, 'Difficilement, en ce moment.', 8, null],
            [Speaker::Seller, 'Vous avez un budget ? Bon, en fait on peut aider.', 12, null],
        ]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::French);

        self::assertSame(2, $figures->questions, 'an unpunctuated "comment" still counts as a question');
        self::assertSame(1, $figures->openQuestions);
        self::assertSame(['euh' => 1, 'du coup' => 1, 'en fait' => 1], $figures->fillerWords);
        self::assertSame(3, $figures->fillers);
        self::assertSame(7, $figures->personaWords);
    }

    public function testFillersAreWholeWords(): void
    {
        $call = self::callWith([[Speaker::Seller, 'Pourquoi pas ? Bah oui, quoi.', 4, null]]);
        $figures = new SpeechAnalyzer()->analyze($call);

        self::assertSame(['bah' => 1, 'quoi' => 1], $figures->fillerWords, '"pourquoi" does not count as "quoi"');
    }

    public function testFillerWordsAreTheFiveMostFrequent(): void
    {
        $call = self::callWith([[Speaker::Seller, 'um um um uh uh like like you know i mean basically actually', 4, null]]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::English);

        self::assertSame(11, $figures->fillers);
        self::assertCount(5, $figures->fillerWords);
        self::assertSame(['um' => 3, 'uh' => 2, 'like' => 2], \array_slice($figures->fillerWords, 0, 3, true));
    }

    public function testSpokenCall(): void
    {
        $call = self::callWith([
            [Speaker::Seller, 'one two three four five six seven eight nine ten', 10, 4000],
            [Speaker::Persona, 'Okay.', 15, null],
            [Speaker::Seller, 'one two three four five', 25, 2000],
            [Speaker::Seller, 'too short to count', 26, 300],
        ]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::English);

        self::assertTrue($figures->voice);
        // 15 words in 6 seconds.
        self::assertSame(150, $figures->wordsPerMinute);
        // (10 - 0 - 4) and (25 - 15 - 2): 6 and 8 seconds.
        self::assertSame(7, $figures->replyDelay);
    }

    public function testACallWithoutTheSeller(): void
    {
        $figures = new SpeechAnalyzer()->analyze(self::call());

        self::assertSame(0, $figures->sellerWords);
        self::assertSame(3, $figures->personaWords);
        self::assertSame(0, $figures->talkShare);
        self::assertSame(0, $figures->longest);
        self::assertSame(0, $figures->questions);
        self::assertNull($figures->replyDelay);
        self::assertNull($figures->wordsPerMinute);
    }

    public function testFiguresRoundTrip(): void
    {
        $call = self::callWith([[Speaker::Seller, 'Um, what do you need?', 5, 1500]]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::English);

        self::assertEquals($figures, \App\Call\Speech\SpeechFigures::fromArray($figures->toArray()));
        self::assertStringContainsString('1 question(s) of which 1 open', $figures->describe());
        self::assertStringContainsString('(um)', $figures->describe());
        self::assertStringContainsString('by voice', $figures->describe());
    }

    public function testAnAffirmationIsNotAnOpenQuestionInFrench(): void
    {
        $call = self::callWith([[Speaker::Seller, "J'ai quelque chose à vous montrer.", 4, null]]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::French);

        self::assertSame(0, $figures->questions);
    }

    public function testAnAffirmationIsNotAnOpenQuestionInEnglish(): void
    {
        $call = self::callWith([[Speaker::Seller, 'However, we can deliver next week.', 4, null]]);
        $figures = new SpeechAnalyzer()->analyze($call, Language::English);

        self::assertSame(0, $figures->questions);
    }
}
