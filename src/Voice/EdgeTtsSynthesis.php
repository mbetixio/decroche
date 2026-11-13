<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\Process\Process;

/**
 * An edge-tts process writing an MP3 to a temporary file, moved into the cache once done (or a cache hit). The
 * read-aloud service now and then refuses a request: a failed synthesis is tried once more before the browser's own
 * voice takes over.
 */
final class EdgeTtsSynthesis implements Synthesis
{
    public function __construct(
        private readonly string $file,
        private ?Process $process,
        private readonly string $part,
        private int $retries = 1,
    ) {
    }

    public function isDone(): bool
    {
        return null === $this->process || !$this->process->isRunning();
    }

    public function wait(): void
    {
        $this->process?->wait();
    }

    public function audio(): ?string
    {
        if (null !== $this->process && !is_file($this->file)) {
            if (!$this->process->isSuccessful() || !is_file($this->part) || 0 === filesize($this->part)) {
                @unlink($this->part);
                if ($this->retries-- <= 0) {
                    return null;
                }
                // Once more, the same command: the service's refusals rarely last.
                $this->process = $this->process->restart();
                $this->process->wait();

                return $this->audio();
            }
            rename($this->part, $this->file);
        }

        return is_file($this->file) ? (string) file_get_contents($this->file) : null;
    }
}
