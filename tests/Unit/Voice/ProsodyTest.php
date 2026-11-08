<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\Prosody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A voice's rate and pitch: defaults and validation.
 */
final class ProsodyTest extends TestCase
{
    public function testDefaults(): void
    {
        $prosody = new Prosody();

        self::assertSame('+12%', $prosody->rate);
        self::assertSame('+0Hz', $prosody->pitch);
    }

    public function testValid(): void
    {
        $prosody = new Prosody('-5%', '+12Hz');

        self::assertSame('-5%', $prosody->rate);
        self::assertSame('+12Hz', $prosody->pitch);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield ['12%', '+0Hz'];
        yield ['+100%', '+0Hz'];
        yield ['+5', '+0Hz'];
        yield ['+5%', '0Hz'];
        yield ['+5%', '+2hz'];
        yield ['+5%', '+200Hz'];
        yield ['+5%"/><evil', '+0Hz'];
        yield ['', ''];
    }

    #[DataProvider('invalid')]
    public function testInvalid(string $rate, string $pitch): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Prosody($rate, $pitch);
    }
}
