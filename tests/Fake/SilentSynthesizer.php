<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Voice\Prosody;
use App\Voice\SpeechSynthesizer;
use App\Voice\Synthesis;
use App\Voice\SynthesisException;

/**
 * No voice in tests: the browser's voice would take over.
 */
final class SilentSynthesizer implements SpeechSynthesizer
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function speak(string $text, string $voice, Prosody $prosody): string
    {
        throw new SynthesisException('No voice in tests.');
    }

    public function start(string $text, string $voice, Prosody $prosody): Synthesis
    {
        throw new SynthesisException('No voice in tests.');
    }
}
