<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Enum\Speaker;
use App\Model\Line;
use PHPUnit\Framework\TestCase;

/**
 * A line of a call: its array round trip, with and without a voice duration, and who said it.
 */
final class LineTest extends TestCase
{
    public function testRoundTripWithVoice(): void
    {
        $line = new Line(Speaker::Seller, 'Hello', new \DateTimeImmutable('2026-11-13T10:00:00+00:00'), 1200);
        $data = $line->toArray();

        self::assertSame(['role' => 'seller', 'content' => 'Hello', 'at' => '2026-11-13T10:00:00+00:00', 'voice' => ['ms' => 1200]], $data);
        self::assertEquals($line, Line::fromArray($data));
    }

    public function testRoundTripWithoutVoice(): void
    {
        $line = new Line(Speaker::Persona, 'Hi', new \DateTimeImmutable('2026-11-13T10:00:05+01:00'));
        $data = $line->toArray();

        self::assertArrayNotHasKey('voice', $data);
        $back = Line::fromArray($data);
        self::assertSame(Speaker::Persona, $back->speaker);
        self::assertSame('Hi', $back->text);
        self::assertSame($line->at->getTimestamp(), $back->at->getTimestamp());
        self::assertNull($back->voiceMs);
    }

    public function testIsSeller(): void
    {
        $at = new \DateTimeImmutable();

        self::assertTrue(new Line(Speaker::Seller, '', $at)->isSeller());
        self::assertFalse(new Line(Speaker::Persona, '', $at)->isSeller());
    }
}
