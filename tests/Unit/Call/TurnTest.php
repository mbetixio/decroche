<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call;

use App\Call\Turn;
use App\Enum\CallEnd;
use PHPUnit\Framework\TestCase;

/**
 * The outcome of an exchange, as sent to the browser.
 */
final class TurnTest extends TestCase
{
    public function testToArray(): void
    {
        self::assertSame(
            ['reply' => 'Bye.', 'ended' => true, 'endedBy' => 'persona', 'secondsLeft' => 42, 'revealed' => 3],
            new Turn('Bye.', true, CallEnd::Persona, 42, 3)->toArray(),
        );
        self::assertSame(
            ['reply' => 'Go on.', 'ended' => false, 'endedBy' => null, 'secondsLeft' => 300, 'revealed' => 0],
            new Turn('Go on.', false, null, 300, 0)->toArray(),
        );
    }
}
