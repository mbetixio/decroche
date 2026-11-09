<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The grade of one criterion of the scorecard.
 */
final class CriterionGrade extends Output
{
    public function __construct(
        #[Schema(description: 'The criterion\'s key, as given')]
        public string $key,
        #[Schema(minimum: 0, maximum: 10)]
        public int $score,
        #[Schema(description: 'One factual sentence, based on the conversation')]
        public string $comment,
    ) {
    }
}
