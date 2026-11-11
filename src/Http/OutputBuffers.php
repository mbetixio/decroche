<?php

declare(strict_types=1);

namespace App\Http;

/**
 * For a streamed response: the output buffers are flushed and closed, so that each piece leaves at once instead of
 * waiting in a buffer. Not on the command line, where the test client collects the whole stream in its own buffer.
 */
final class OutputBuffers
{
    /**
     * @param string $sapi  the server API (the current one by default)
     * @param int    $floor the buffers to keep, counted from the bottom
     */
    public static function release(string $sapi = \PHP_SAPI, int $floor = 0): void
    {
        if ('cli' === $sapi) {
            return;
        }
        while (ob_get_level() > $floor) {
            ob_end_flush();
        }
    }
}
