<?php

declare(strict_types=1);

namespace App\Voice;

/**
 * A speech synthesis under way.
 */
interface Synthesis
{
    public function isDone(): bool;

    public function wait(): void;

    /**
     * The MP3 once done; null when it failed.
     */
    public function audio(): ?string;
}
