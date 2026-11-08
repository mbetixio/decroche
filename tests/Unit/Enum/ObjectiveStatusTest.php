<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ObjectiveStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * An objective's status: read from the model in English or French, its weight in the score and its XP bonus.
 */
final class ObjectiveStatusTest extends TestCase
{
    /**
     * @return iterable<array{mixed, ObjectiveStatus}>
     */
    public static function values(): iterable
    {
        yield ['reached', ObjectiveStatus::Reached];
        yield [' Reached!', ObjectiveStatus::Reached];
        yield ['atteint', ObjectiveStatus::Reached];
        yield ['partial', ObjectiveStatus::Partial];
        yield ['partially', ObjectiveStatus::Partial];
        yield ['Partiellement', ObjectiveStatus::Partial];
        yield ['missed', ObjectiveStatus::Missed];
        yield ['nope', ObjectiveStatus::Missed];
        yield [null, ObjectiveStatus::Missed];
        yield [true, ObjectiveStatus::Missed];
    }

    #[DataProvider('values')]
    public function testFromModel(mixed $value, ObjectiveStatus $expected): void
    {
        self::assertSame($expected, ObjectiveStatus::fromModel($value));
    }

    public function testWeight(): void
    {
        self::assertSame(1.0, ObjectiveStatus::Reached->weight());
        self::assertSame(0.5, ObjectiveStatus::Partial->weight());
        self::assertSame(0.0, ObjectiveStatus::Missed->weight());
    }

    public function testBonus(): void
    {
        self::assertSame(10, ObjectiveStatus::Reached->bonus());
        self::assertSame(4, ObjectiveStatus::Partial->bonus());
        self::assertSame(0, ObjectiveStatus::Missed->bonus());
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.objective_status.partial', ObjectiveStatus::Partial->trans(new IdentityTranslator()));
    }
}
