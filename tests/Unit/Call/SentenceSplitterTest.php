<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call;

use App\Call\SentenceSplitter;
use PHPUnit\Framework\TestCase;

/**
 * Cutting a streamed reply into sentences: at final punctuation followed by a blank, the first one early at a comma,
 * and the rest when the reply is complete.
 */
final class SentenceSplitterTest extends TestCase
{
    public function testSplitsCompleteSentences(): void
    {
        $splitter = new SentenceSplitter();

        self::assertSame(['Hello.', 'How are you?'], $splitter->feed('Hello. How are you? I am'));
        self::assertSame([], $splitter->feed(' fine'));
        self::assertSame('I am fine', $splitter->finish());
        self::assertNull($splitter->finish());
    }

    public function testWaitsForTheBlankAfterThePunctuation(): void
    {
        $splitter = new SentenceSplitter();

        self::assertSame([], $splitter->feed('It costs 3.5'));
        self::assertSame([], $splitter->feed('k a month!'));
        self::assertSame(['It costs 3.5k a month!'], $splitter->feed(' Right'));
        self::assertSame('Right', $splitter->finish());
    }

    public function testKeepsClosingQuotesAndEllipses(): void
    {
        $splitter = new SentenceSplitter();

        self::assertSame(['He said « no ».»', 'Well…'], $splitter->feed('He said « no ».» Well… then'));
        self::assertSame(['Really?!)'], new SentenceSplitter()->feed('Really?!) Yes'));
    }

    public function testTheFirstSentenceLeavesAtACommaOnceLongEnough(): void
    {
        $splitter = new SentenceSplitter();

        self::assertSame(['Well, I have been thinking about it,'], $splitter->feed('Well, I have been thinking about it, and honestly'));
        self::assertSame([], $splitter->feed(', with the budget we have, it is hard'));
        self::assertSame(['and honestly, with the budget we have, it is hard.'], $splitter->feed('. '));
    }

    public function testAShortFirstClauseDoesNotLeaveAtAComma(): void
    {
        self::assertSame([], new SentenceSplitter()->feed('Yes, sure, '));
    }

    public function testFinishWithNothingLeft(): void
    {
        $splitter = new SentenceSplitter();
        self::assertNull($splitter->finish());

        $splitter->feed('Done. ');
        self::assertNull($splitter->finish());
    }
}
