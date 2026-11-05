<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who or what ended a call.
 */
enum CallEnd: string
{
    /** The seller hung up. */
    case Seller = 'seller';

    /** The meeting ran out of time, or of turns. */
    case Clock = 'clock';

    /** The persona hung up (the next step was agreed, or the seller wasted their time). */
    case Persona = 'persona';
}
