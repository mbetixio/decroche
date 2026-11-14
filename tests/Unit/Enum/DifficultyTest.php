<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Difficulty;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Difficulty: the length of a surprise call, the persona's instruction, and the difficulties drawn around a target.
 */
final class DifficultyTest extends TestCase
{
    public function testMinutesGrowWithDifficulty(): void
    {
        self::assertSame(8, Difficulty::Approachable->minutes());
        self::assertSame(10, Difficulty::Demanding->minutes());
        self::assertSame(12, Difficulty::Tough->minutes());
    }

    public function testInstruction(): void
    {
        foreach (Difficulty::cases() as $difficulty) {
            self::assertStringStartsWith('Difficulty:', $difficulty->instruction());
        }
    }

    public function testAroundIsMostlyItselfWithANeighbour(): void
    {
        self::assertSame([Difficulty::Approachable, Difficulty::Approachable, Difficulty::Demanding], Difficulty::Approachable->around());
        self::assertSame([Difficulty::Approachable, Difficulty::Demanding, Difficulty::Demanding, Difficulty::Tough], Difficulty::Demanding->around());
        self::assertSame([Difficulty::Demanding, Difficulty::Tough, Difficulty::Tough], Difficulty::Tough->around());
    }

    public function testAnApproachableTargetNeverDrawsToughAndAToughOneNeverApproachable(): void
    {
        self::assertNotContains(Difficulty::Tough, Difficulty::Approachable->around());
        self::assertNotContains(Difficulty::Approachable, Difficulty::Tough->around());
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.difficulty.3', Difficulty::Tough->trans(new IdentityTranslator()));
    }
}
