<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * A line of the seller, quoted word for word, with the coach's view on it. For a good line, "better" stays empty.
 */
final class QuotedLine extends Output
{
    public function __construct(
        #[Schema(description: 'A line of the seller, quoted word for word')]
        public string $quote,
        #[Schema(description: 'Why it is good, or what is wrong')]
        public string $why,
        #[Schema(description: 'For a line to rework: what they could have said instead; otherwise empty')]
        public string $better,
    ) {
    }
}
