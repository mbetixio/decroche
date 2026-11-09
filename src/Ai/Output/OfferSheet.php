<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The company's offer sheet, drafted from its website at onboarding (App\Company\OfferAnalyst::offer()).
 */
final class OfferSheet extends Output
{
    /**
     * @param list<string> $sectors
     */
    public function __construct(
        #[Schema(description: 'The company\'s name')]
        public string $name,
        #[Schema(description: 'The sheet, in plain text, one paragraph per section')]
        public string $offer,
        #[Schema(description: '10 to 15 sectors or types of clients the company serves or targets, lowercase')]
        public array $sectors,
    ) {
    }
}
