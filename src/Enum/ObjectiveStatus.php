<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How far a call reached one of its objectives.
 */
enum ObjectiveStatus: string implements TranslatableInterface
{
    case Reached = 'reached';
    case Partial = 'partial';
    case Missed = 'missed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.objective_status.'.$this->value, locale: $locale);
    }

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

    /**
     * Reads a status written by the model, in English or French.
     */
    public static function fromModel(mixed $value): self
    {
        $value = \is_string($value) ? mb_strtolower(trim($value)) : '';

        return match (true) {
            str_starts_with($value, 'reach'), str_starts_with($value, 'atteint') => self::Reached,
            str_starts_with($value, 'partial'), str_starts_with($value, 'partiel') => self::Partial,
            default => self::Missed,
        };
    }
}
