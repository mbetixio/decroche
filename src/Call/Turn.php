<?php

declare(strict_types=1);

namespace App\Call;

use App\Enum\CallEnd;

/**
 * The outcome of one exchange: the persona's reply, and where the call stands after it.
 */
final readonly class Turn
{
    public function __construct(
        public string $reply,
        public bool $ended,
        public ?CallEnd $endedBy,
        public int $secondsLeft,
        /** How many brief sections the seller has found so far. */
        public int $revealed,
    ) {
    }

    /**
     * @return array{reply: string, ended: bool, endedBy: ?string, secondsLeft: int, revealed: int}
     */
    public function toArray(): array
    {
        return ['reply' => $this->reply, 'ended' => $this->ended, 'endedBy' => $this->endedBy?->value, 'secondsLeft' => $this->secondsLeft, 'revealed' => $this->revealed];
    }
}
