<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Microsoft Edge's neural voices (Denise, Henri…), synthesized server-side by edge-tts, the reference library for
 * Edge's read-aloud service: the seller's browser no longer needs good voices of its own. The MP3s are cached, so a
 * line already said is never synthesized twice.
 *
 * The read-aloud service is not a documented public API: Microsoft changes its access token now and then, and
 * "pip install -U edge-tts" follows. When it fails, the browser's own voice takes over.
 */
#[AsAlias(SpeechSynthesizer::class)]
final readonly class EdgeTtsSynthesizer implements SpeechSynthesizer
{
    public function __construct(
        #[Autowire('%env(default:edge_tts_default:EDGE_TTS)%')]
        private string $binary,
        #[Autowire('%env(default:voices_dir:VOICES_DIR)%')]
        private string $cache,
    ) {
    }

    public function isAvailable(): bool
    {
        return is_file($this->binary) && is_executable($this->binary);
    }

    public function speak(string $text, string $voice, Prosody $prosody): string
    {
        $synthesis = $this->start($text, $voice, $prosody);
        $synthesis->wait();

        return $synthesis->audio() ?? throw new SynthesisException('The voice could not be synthesized.');
    }

    public function start(string $text, string $voice, Prosody $prosody): Synthesis
    {
        $text = trim($text);
        if ('' === $text) {
            throw new SynthesisException('Nothing to say.');
        }
        if (!preg_match('/^[a-z]{2}-[A-Z]{2}-[A-Za-z]+Neural$/', $voice)) {
            throw new SynthesisException(\sprintf('Unknown voice "%s".', $voice));
        }
        if (!$this->isAvailable()) {
            throw new SynthesisException('edge-tts is not installed (see the README).');
        }
        $file = \sprintf('%s/%s.mp3', $this->cache, sha1($voice.$prosody->rate.$prosody->pitch."\n".$text));
        if (is_file($file) && filesize($file) > 0) {
            return new EdgeTtsSynthesis($file, null, '');
        }
        if (!is_dir($this->cache)) {
            @mkdir($this->cache, 0o775, true);
        }
        $part = $file.'.'.bin2hex(random_bytes(3)).'.part';
        $process = new Process([$this->binary, '--voice', $voice, '--rate='.$prosody->rate, '--pitch='.$prosody->pitch, '--text', $text, '--write-media', $part]);
        $process->setTimeout(30);
        $process->start();

        return new EdgeTtsSynthesis($file, $process, $part);
    }
}
