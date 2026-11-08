<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Difficulty;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Difficulty: the length of a surprise call, the persona's instruction, and the draw by seller level.
 */
final class DifficultyTest extends TestCase
{
    public function testMinutesGrowWithDifficulty(): void
    {
        self::assertSame(8, Difficulty::Easy->minutes());
        self::assertSame(10, Difficulty::Demanding->minutes());
        self::assertSame(12, Difficulty::Tough->minutes());
    }

    public function testInstruction(): void
    {
        foreach (Difficulty::cases() as $difficulty) {
            self::assertStringStartsWith('Difficulty:', $difficulty->instruction());
        }
    }

    public function testForLevelGetsHarder(): void
    {
        self::assertSame([Difficulty::Easy, Difficulty::Easy, Difficulty::Demanding], Difficulty::forLevel(0));
        self::assertSame([Difficulty::Easy, Difficulty::Easy, Difficulty::Demanding], Difficulty::forLevel(1));
        self::assertSame([Difficulty::Easy, Difficulty::Demanding, Difficulty::Demanding, Difficulty::Tough], Difficulty::forLevel(2));
        self::assertSame([Difficulty::Easy, Difficulty::Demanding, Difficulty::Demanding, Difficulty::Tough], Difficulty::forLevel(3));
        self::assertSame([Difficulty::Demanding, Difficulty::Tough, Difficulty::Tough], Difficulty::forLevel(4));
        self::assertSame([Difficulty::Demanding, Difficulty::Tough, Difficulty::Tough], Difficulty::forLevel(7));
    }

    public function testBeginnersNeverDrawToughAndVeteransNeverDrawEasy(): void
    {
        self::assertNotContains(Difficulty::Tough, Difficulty::forLevel(1));
        self::assertNotContains(Difficulty::Easy, Difficulty::forLevel(5));
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.difficulty.3', Difficulty::Tough->trans(new IdentityTranslator()));
    }
}
