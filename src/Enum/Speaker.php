<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who says a line of a call.
 */
enum Speaker: string
{
    case Seller = 'seller';
    case Persona = 'persona';
}
