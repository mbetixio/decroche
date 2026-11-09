<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * One call situation of the company's catalogue.
 */
final class SituationIdea extends Output
{
    /**
     * @param list<string> $objectives
     */
    public function __construct(
        #[Schema(description: 'A short identifier in snake_case')]
        public string $key,
        #[Schema(description: 'A short title')]
        public string $label,
        #[Schema(description: 'One emoji')]
        public string $emoji,
        #[Schema(enum: ['prospecting', 'client', 'negotiation'])]
        public string $family,
        #[Schema(description: 'outgoing: the seller calls; incoming: the seller is called', enum: ['outgoing', 'incoming'])]
        public string $direction,
        #[Schema(description: 'The document the seller has in front of them before the call')]
        public string $material,
        #[Schema(description: '3 or 4 sentences: who is on the other end, what they want, what is stuck, what is hidden')]
        public string $pitch,
        #[Schema(description: 'Three concrete, checkable objectives for the call', minItems: 1, maxItems: 3)]
        public array $objectives,
    ) {
    }
}
