<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The voices the app can use: Kyutai's Pocket TTS, offline, when its server runs (as in Docker), Microsoft Edge's
 * through edge-tts otherwise. The voice says which one speaks it ("pocket:…" or "…Neural"), as cast by VoiceCasting.
 */
#[AsAlias(SpeechSynthesizer::class)]
final readonly class Synthesizers implements SpeechSynthesizer
{
    public function __construct(
        private PocketTtsSynthesizer $pocket,
        private EdgeTtsSynthesizer $edge,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->pocket->isAvailable() || $this->edge->isAvailable();
    }

    public function speak(string $text, string $voice, Prosody $prosody): string
    {
        return $this->of($voice)->speak($text, $voice, $prosody);
    }

    public function start(string $text, string $voice, Prosody $prosody): Synthesis
    {
        return $this->of($voice)->start($text, $voice, $prosody);
    }

    private function of(string $voice): SpeechSynthesizer
    {
        return str_starts_with($voice, PocketTtsSynthesizer::PREFIX) ? $this->pocket : $this->edge;
    }
}
