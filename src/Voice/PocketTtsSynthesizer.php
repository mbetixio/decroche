<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Kyutai's Pocket TTS, an open model that speaks on the CPU, offline: a server started next to the app for the
 * instance's language keeps the model in memory, and each line is one request to it. Its English voices are built in
 * ("pocket:jane"); the French ones were exported from native speakers' recordings and are served by the app itself
 * ("pocket:fr/f-2154…", under public/voices/), as the server only loads voices by URL. The WAVs are cached like the
 * edge-tts MP3s.
 */
final class PocketTtsSynthesizer implements SpeechSynthesizer, ResetInterface
{
    public const string PREFIX = 'pocket:';

    /** Whether the server answered, asked once per request. */
    private ?bool $up = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        /** The Pocket TTS server ("http://127.0.0.1:8100"); empty when there is none. */
        #[Autowire('%env(string:default::POCKET_TTS_URL)%')]
        private readonly string $url,
        /** Where the server fetches the exported voices: this app's public/voices/, as seen from the server. */
        #[Autowire('%env(default:pocket_voices_default:POCKET_VOICES_URL)%')]
        private readonly string $voices,
        #[Autowire('%env(default:voices_dir:VOICES_DIR)%')]
        private readonly string $cache,
        private readonly VoiceCachePruner $pruner,
        /** The cache is pruned once in so many new lines: no cron needed. */
        private readonly int $pruneOneIn = 200,
    ) {
    }

    public function isAvailable(): bool
    {
        if ('' === $this->url) {
            return false;
        }
        try {
            return $this->up ??= 200 === $this->http->request('GET', rtrim($this->url, '/').'/health', ['timeout' => 1])->getStatusCode();
        } catch (ExceptionInterface) {
            return $this->up = false;
        }
    }

    /**
     * Between requests, the server is asked again: it may have started, or stopped.
     */
    public function reset(): void
    {
        $this->up = null;
    }

    public function speak(string $text, string $voice, Prosody $prosody): string
    {
        $synthesis = $this->start($text, $voice, $prosody);
        $synthesis->wait();

        return $synthesis->audio() ?? throw new SynthesisException('The voice could not be synthesized.');
    }

    /**
     * The prosody is not used: Pocket TTS has no rate or pitch, the browser plays its voices a little faster.
     */
    public function start(string $text, string $voice, Prosody $prosody): Synthesis
    {
        $text = trim($text);
        if ('' === $text) {
            throw new SynthesisException('Nothing to say.');
        }
        if (!preg_match('#^'.self::PREFIX.'([a-z_]+|[a-z]{2}/[a-z0-9_-]+)$#', $voice, $m)) {
            throw new SynthesisException(\sprintf('Unknown voice "%s".', $voice));
        }
        if (!$this->isAvailable()) {
            throw new SynthesisException('The Pocket TTS server is not running (see the README).');
        }
        // Pocket TTS reads the text as written: numbers and symbols are said in words first.
        $text = new SpokenText(str_contains($m[1], '/') ? substr($m[1], 0, 2) : 'en')->of($text);
        $file = \sprintf('%s/%s.wav', $this->cache, sha1($voice."\n".$text));
        if (is_file($file) && filesize($file) > 0) {
            // Heard again: the pruner keeps it.
            @touch($file);

            return new PocketTtsSynthesis($this->http, $file, null);
        }
        if (1 === random_int(1, max(1, $this->pruneOneIn))) {
            $this->pruner->prune();
        }
        if (!is_dir($this->cache)) {
            @mkdir($this->cache, 0o775, true);
        }
        // A built-in voice by its name, an exported one by its URL.
        $source = str_contains($m[1], '/') ? rtrim($this->voices, '/').'/'.$m[1].'.safetensors' : $m[1];
        $response = $this->http->request('POST', rtrim($this->url, '/').'/tts', [
            'body' => ['text' => $text, 'voice_url' => $source],
            'timeout' => 30,
        ]);

        return new PocketTtsSynthesis($this->http, $file, $response);
    }
}
