<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How far a call reached one of its objectives.
 */
enum ObjectiveStatus: string
{
    case Reached = 'atteint';
    case Partial = 'partiel';
    case Missed = 'manqué';

    /**
     * Its weight in the score: a reached objective counts fully, a partial one half.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Reached => 1.0,
            self::Partial => 0.5,
            self::Missed => 0.0,
        };
    }

    /**
     * The experience points it brings on top of the score.
     */
    public function bonus(): int
    {
        return match ($this) {
            self::Reached => 10,
            self::Partial => 4,
            self::Missed => 0,
        };
    }

    public static function fromModel(mixed $value): self
    {
        return self::tryFrom(\is_string($value) ? mb_strtolower(trim($value)) : '') ?? match (true) {
            \is_string($value) && str_starts_with(mb_strtolower($value), 'manqu') => self::Missed,
            default => self::Missed,
        };
    }
}
