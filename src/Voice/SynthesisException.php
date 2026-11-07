<?php

declare(strict_types=1);

namespace App\Voice;

/**
 * The speech could not be synthesized (the service is down, edge-tts is not installed…).
 */
final class SynthesisException extends \RuntimeException
{
}
