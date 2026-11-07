<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\Process\Process;

/**
 * An edge-tts process writing an MP3 to a temporary file, moved into the cache once done (or a cache hit).
 */
final class EdgeTtsSynthesis implements Synthesis
{
    public function __construct(
        private readonly string $file,
        private readonly ?Process $process,
        private readonly string $part,
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
        if (null !== $this->process && is_file($this->part)) {
            if (!$this->process->isSuccessful() || 0 === filesize($this->part)) {
                @unlink($this->part);

                return null;
            }
            rename($this->part, $this->file);
        }

        return is_file($this->file) ? (string) file_get_contents($this->file) : null;
    }
}
