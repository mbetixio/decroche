<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\CallDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Reading a call direction written by the model (French values included), and its translation key.
 */
final class CallDirectionTest extends TestCase
{
    /**
     * @return iterable<array{mixed, CallDirection}>
     */
    public static function values(): iterable
    {
        yield ['incoming', CallDirection::Incoming];
        yield [' Entrant ', CallDirection::Incoming];
        yield ['INBOUND', CallDirection::Incoming];
        yield ['outgoing', CallDirection::Outgoing];
        yield ['sortant', CallDirection::Outgoing];
        yield ['', CallDirection::Outgoing];
        yield [null, CallDirection::Outgoing];
        yield [42, CallDirection::Outgoing];
    }

    #[DataProvider('values')]
    public function testFromModel(mixed $value, CallDirection $expected): void
    {
        self::assertSame($expected, CallDirection::fromModel($value));
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.call_direction.incoming', CallDirection::Incoming->trans(new IdentityTranslator()));
    }
}
