<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\OutputBuffers;
use PHPUnit\Framework\TestCase;

final class OutputBuffersTest extends TestCase
{
    public function testOnAServerEveryBufferIsFlushed(): void
    {
        $floor = ob_get_level();
        ob_start();
        ob_start();
        echo 'the first sentence';

        OutputBuffers::release('frankenphp', $floor);

        self::assertSame($floor, ob_get_level());
        $this->expectOutputString('the first sentence');
    }

    public function testOnTheCommandLineTheBuffersStay(): void
    {
        ob_start();
        $level = ob_get_level();

        OutputBuffers::release('cli');

        self::assertSame($level, ob_get_level());
        ob_end_clean();
    }
}
