<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The three families of call situations.
 */
enum SituationFamily: string
{
    case Prospecting = 'prospection';
    case Client = 'client';
    case Negotiation = 'negociation';

    public function label(): string
    {
        return match ($this) {
            self::Prospecting => 'Prospection',
            self::Client => 'Relation client',
            self::Negotiation => 'Négociation',
        };
    }
}
