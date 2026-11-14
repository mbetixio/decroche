<?php

declare(strict_types=1);

namespace App\Security;

/**
 * What a member asks of the model, each kind with its own quota (see config/packages/rate_limiter.yaml): a call written
 * by the writer agent, a line answered by the persona, a debrief written by the coach.
 */
enum Usage: string
{
    case Writing = 'writing';
    case Talking = 'talking';
    case Coaching = 'coaching';
}
