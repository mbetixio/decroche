<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A request to the Pocket TTS server, its WAV written to the cache once received (or a cache hit). Asking whether it
 * is done lets it progress without waiting, so the sentences of a reply are voiced while the next ones are written.
 */
final class PocketTtsSynthesis implements Synthesis
{
    private bool $done;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $file,
        private readonly ?ResponseInterface $response,
    ) {
        $this->done = null === $response;
    }

    public function isDone(): bool
    {
        if (!$this->done && null !== $this->response) {
            try {
                foreach ($this->http->stream($this->response, 0.0) as $chunk) {
                    if ($chunk->isTimeout()) {
                        break;
                    }
                    if ($chunk->isLast()) {
                        $this->done = true;
                    }
                }
            } catch (ExceptionInterface) {
                $this->done = true;
            }
        }

        return $this->done;
    }

    public function wait(): void
    {
        if (null !== $this->response) {
            try {
                $this->response->getContent(false);
            } catch (ExceptionInterface) {
            }
        }
        $this->done = true;
    }

    public function audio(): ?string
    {
        if (null !== $this->response && !is_file($this->file)) {
            try {
                $audio = 200 === $this->response->getStatusCode() ? $this->response->getContent(false) : '';
            } catch (ExceptionInterface) {
                $audio = '';
            }
            if (!str_starts_with($audio, 'RIFF')) {
                return null;
            }
            $part = $this->file.'.'.bin2hex(random_bytes(3)).'.part';
            file_put_contents($part, $audio);
            rename($part, $this->file);
        }

        return is_file($this->file) ? (string) file_get_contents($this->file) : null;
    }
}
