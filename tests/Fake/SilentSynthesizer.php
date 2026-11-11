<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Voice\Prosody;
use App\Voice\SpeechSynthesizer;
use App\Voice\Synthesis;
use App\Voice\SynthesisException;

/**
 * The voices in tests: none by default (the browser's voice takes over), or, when a test turns them on, a tiny
 * stand-in audio that takes one wait to be ready, as a real synthesis does; or a synthesis that fails.
 */
final class SilentSynthesizer implements SpeechSynthesizer
{
    /** Whether the voices are on. */
    public static bool $voiced = false;

    /** Whether every synthesis fails. */
    public static bool $failing = false;

    public static function reset(): void
    {
        self::$voiced = false;
        self::$failing = false;
    }

    public function isAvailable(): bool
    {
        return self::$voiced;
    }

    public function speak(string $text, string $voice, Prosody $prosody): string
    {
        $synthesis = $this->start($text, $voice, $prosody);
        $synthesis->wait();

        return $synthesis->audio() ?? throw new SynthesisException('The voice could not be synthesized.');
    }

    public function start(string $text, string $voice, Prosody $prosody): Synthesis
    {
        if (!self::$voiced) {
            throw new SynthesisException('No voice in tests.');
        }

        return new class(self::$failing ? null : 'MP3:'.$text) implements Synthesis {
            private bool $done = false;

            public function __construct(private readonly ?string $audio)
            {
            }

            public function isDone(): bool
            {
                return $this->done;
            }

            public function wait(): void
            {
                $this->done = true;
            }

            public function audio(): ?string
            {
                return $this->done ? $this->audio : null;
            }
        };
    }
}
