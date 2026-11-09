<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * A section of the hidden brief the seller did not discover, and how they could have.
 */
final class MissedSection extends Output
{
    public function __construct(
        #[Schema(enum: ['budget', 'pain', 'decision', 'objections', 'yes', 'secret'])]
        public string $rubric,
        #[Schema(description: 'What was to be discovered')]
        public string $what,
        #[Schema(description: 'The question that would have brought it out')]
        public string $how,
    ) {
    }
}
