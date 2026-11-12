<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The tools of the agents under test: a calendar to look at, and an offer to read.
 */
#[AsTool('calendar', 'Your calendar on a day.', method: 'calendar')]
#[AsTool('read_offer', 'Read the offer.', method: 'offer')]
final class LanguageModelTestTools
{
    public function calendar(string $day): string
    {
        return "Free at nine on {$day}.";
    }

    public function offer(string $topic = ''): string
    {
        return 'Dedicated teams for retailers.';
    }
}
