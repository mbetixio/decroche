<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Persona;

use App\Call\Persona\ReplyFilter;
use App\Enum\BriefSection;
use PHPUnit\Framework\TestCase;

/**
 * Cleaning the persona's streamed reply: tags applied and removed (even when split across chunks), stage directions
 * dropped, the persona's name and the quotation marks removed.
 */
final class ReplyFilterTest extends TestCase
{
    /**
     * @param list<string> $chunks
     */
    private static function stream(ReplyFilter $filter, array $chunks): string
    {
        $out = '';
        foreach ($chunks as $chunk) {
            $out .= $filter->feed($chunk);
        }

        return $out.$filter->finish();
    }

    public function testPlainReplyGoesThrough(): void
    {
        $filter = new ReplyFilter('Claire Dubois');

        self::assertSame('Hello, how can I help you today?', self::stream($filter, ['Hello, how can ', 'I help you today?']));
        self::assertSame('Hello, how can I help you today?', $filter->text());
        self::assertSame([], $filter->revealed);
        self::assertFalse($filter->hungUp);
    }

    public function testRevealTagsAreReportedAndRemoved(): void
    {
        $filter = new ReplyFilter('Claire Dubois');
        $out = self::stream($filter, ['[devoiler:budget][dévoiler: pain] Honestly, we have about 50k and our lead developer is leaving.']);

        self::assertSame([BriefSection::Budget, BriefSection::Pain], $filter->revealed);
        self::assertStringNotContainsString('[', $out);
        self::assertSame('Honestly, we have about 50k and our lead developer is leaving.', $filter->text());
    }

    public function testATagSplitAcrossChunksIsHeldBack(): void
    {
        $filter = new ReplyFilter('Claire');
        $said = [];
        foreach (['Well, that is the thing, ', 'you see. [devo', 'iler:Secret] We are ', 'being acquired.'] as $chunk) {
            $said[] = $filter->feed($chunk);
        }
        $said[] = $filter->finish();

        foreach ($said as $piece) {
            self::assertStringNotContainsString('[', $piece);
            self::assertStringNotContainsString('devo', $piece);
        }
        self::assertSame([BriefSection::Secret], $filter->revealed);
        self::assertSame('Well, that is the thing, you see. We are being acquired.', $filter->text());
    }

    public function testEndTagHangsUp(): void
    {
        $filter = new ReplyFilter('Claire');
        self::stream($filter, ['Fine, see you Tuesday then. Goodbye! [fin]']);

        self::assertTrue($filter->hungUp);
        self::assertSame('Fine, see you Tuesday then. Goodbye!', $filter->text());
    }

    public function testOtherBracketsAreStageDirectionsAndDropped(): void
    {
        $filter = new ReplyFilter('Claire');
        self::stream($filter, ['[sighs] I really do not have time for this [devoiler:unknown].']);

        self::assertSame([], $filter->revealed);
        self::assertFalse($filter->hungUp);
        self::assertSame('I really do not have time for this .', $filter->text());
    }

    public function testThePersonasNameBeforeTheLineIsDropped(): void
    {
        $filter = new ReplyFilter('Claire Dubois');

        self::assertSame('', $filter->feed('**Claire'));
        self::assertSame('Yes, speaking.', $filter->feed(' Dubois**: Yes, speaking.').$filter->finish());
    }

    public function testQuotationMarksAreRemoved(): void
    {
        $filter = new ReplyFilter('Claire');

        self::assertSame('Yes, I know.', self::stream($filter, ['«Yes, I know.»']));
    }

    public function testAShortReplyIsGivenOnFinish(): void
    {
        $filter = new ReplyFilter('Claire Dubois');

        self::assertSame('', $filter->feed('Yes?'));
        self::assertSame('Yes?', $filter->finish());
        self::assertSame('Yes?', $filter->text());
    }

    public function testAnUnclosedBracketIsGivenOnFinish(): void
    {
        $filter = new ReplyFilter('Claire');
        $out = self::stream($filter, ['I would say [maybe']);

        self::assertSame('I would say [maybe', $out);
    }
}
