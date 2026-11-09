<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * Whether one objective of the call was reached.
 */
final class ObjectiveVerdict extends Output
{
    public function __construct(
        #[Schema(description: 'The objective, as given')]
        public string $objective,
        #[Schema(enum: ['reached', 'partial', 'missed'])]
        public string $status,
        #[Schema(description: 'What in the conversation shows it')]
        public string $evidence,
    ) {
    }
}
