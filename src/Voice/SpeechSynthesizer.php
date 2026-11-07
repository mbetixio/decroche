<?php

declare(strict_types=1);

namespace App\Voice;

/**
 * Turns a line into speech (an MP3). Synthesis can be started without waiting for it, so that the sentences of a
 * streamed reply are voiced while the next ones are written.
 */
interface SpeechSynthesizer
{
    /**
     * Whether speech can be synthesized at all (otherwise the browser's own voice takes over).
     */
    public function isAvailable(): bool;

    /**
     * The MP3 of a line.
     *
     * @throws SynthesisException
     */
    public function speak(string $text, string $voice, Prosody $prosody): string;

    /**
     * Starts the synthesis of a line without waiting for it.
     */
    public function start(string $text, string $voice, Prosody $prosody): Synthesis;
}
