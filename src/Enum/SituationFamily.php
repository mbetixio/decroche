<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The three families of call situations.
 */
enum SituationFamily: string implements TranslatableInterface
{
    case Prospecting = 'prospecting';
    case Client = 'client';
    case Negotiation = 'negotiation';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.situation_family.'.$this->value, locale: $locale);
    }

    /**
     * Reads a family written by the model or an older file (French values included).
     */
    public static function fromModel(mixed $value): self
    {
        return match (\is_string($value) ? mb_strtolower(trim($value)) : '') {
            'client', 'relation client', 'customer' => self::Client,
            'negotiation', 'negociation', 'négociation' => self::Negotiation,
            default => self::Prospecting,
        };
    }
}
