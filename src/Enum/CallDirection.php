<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who dials: the seller calls out, or the client calls in.
 */
enum CallDirection: string
{
    case Outgoing = 'sortant';
    case Incoming = 'entrant';

    public function label(): string
    {
        return match ($this) {
            self::Outgoing => 'Vous appelez',
            self::Incoming => 'On vous appelle',
        };
    }
}
