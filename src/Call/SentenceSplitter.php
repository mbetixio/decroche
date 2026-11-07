<?php

declare(strict_types=1);

namespace App\Call;

/**
 * Cuts a reply streamed piece by piece into sentences, so that each one can be voiced as soon as it is written. A
 * sentence ends with its punctuation followed by a blank; the very first one leaves at a comma once long enough, so the
 * voice starts as early as possible.
 */
final class SentenceSplitter
{
    private string $pending = '';
    private bool $started = false;

    /**
     * Adds a piece of the reply.
     *
     * @return list<string> the sentences it completed
     */
    public function feed(string $piece): array
    {
        $this->pending .= $piece;
        $sentences = [];
        while (preg_match('/^(.+?[.!?…]+[»")]*)\s+(.*)$/su', $this->pending, $m)
            || (!$this->started && preg_match('/^(.{25,}?[,;:])\s+(.*)$/su', $this->pending, $m))) {
            $sentences[] = trim($m[1]);
            $this->pending = $m[2];
            $this->started = true;
        }

        return $sentences;
    }

    /**
     * The rest once the reply is complete, if any.
     */
    public function finish(): ?string
    {
        $rest = trim($this->pending);
        $this->pending = '';

        return '' !== $rest ? $rest : null;
    }
}
