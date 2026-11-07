<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Who dials: the seller calls out, or the client calls in.
 */
enum CallDirection: string implements TranslatableInterface
{
    case Outgoing = 'outgoing';
    case Incoming = 'incoming';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.call_direction.'.$this->value, locale: $locale);
    }

    /**
     * Reads a direction written by the model or an older file (French values included).
     */
    public static function fromModel(mixed $value): self
    {
        $value = \is_string($value) ? mb_strtolower(trim($value)) : '';

        return \in_array($value, ['incoming', 'entrant', 'inbound'], true) ? self::Incoming : self::Outgoing;
    }
}
