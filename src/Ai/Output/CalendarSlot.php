<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * A busy slot of the persona's calendar.
 */
final class CalendarSlot extends Output
{
    public function __construct(
        #[Schema(description: 'Its start, as YYYY-MM-DD HH:MM')]
        public string $when,
        #[Schema(minimum: 15, maximum: 480)]
        public int $minutes,
        #[Schema(description: 'What it is, in a few words')]
        public string $what,
    ) {
    }
}
