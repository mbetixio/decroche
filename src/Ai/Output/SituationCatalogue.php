<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The company's catalogue of call situations (App\Company\OfferAnalyst::situations()).
 */
final class SituationCatalogue extends Output
{
    /**
     * @param list<SituationIdea> $situations
     */
    public function __construct(
        #[Schema(description: '14 to 18 varied, realistic situations', minItems: 5)]
        public array $situations,
    ) {
    }
}
