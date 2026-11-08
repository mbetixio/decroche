<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scenario;

use App\Enum\Difficulty;
use App\Enum\Tone;
use App\Scenario\Draw;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A drawn surprise call holds what it was given.
 */
final class DrawTest extends TestCase
{
    use Fixtures;

    public function testHoldsItsDressing(): void
    {
        $situation = self::situation();
        $draw = new Draw($situation, 'Retail', 'a 15-person startup', 'Lyon', Tone::Busy, Difficulty::Easy);

        self::assertSame($situation, $draw->situation);
        self::assertSame('Retail', $draw->sector);
        self::assertSame('a 15-person startup', $draw->size);
        self::assertSame('Lyon', $draw->city);
        self::assertSame(Tone::Busy, $draw->tone);
        self::assertSame(Difficulty::Easy, $draw->difficulty);
    }
}
