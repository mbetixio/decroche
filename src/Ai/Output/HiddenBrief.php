<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * What the persona knows and the seller must discover through questions: never shown to the seller. The sections are
 * those of App\Enum\BriefSection, plus the persona's own view of the situation.
 */
final class HiddenBrief extends Output
{
    public function __construct(
        #[Schema(description: 'The situation from the persona\'s side: what they know of the seller\'s company, their relationship, what they are doing, their mood')]
        public string $setup,
        #[Schema(description: 'The real budget and its constraints')]
        public string $budget,
        #[Schema(description: 'The real pain, which they do not tell at first')]
        public string $pain,
        #[Schema(description: 'Who really decides, the process, the timeline')]
        public string $decision,
        #[Schema(description: '2 or 3 objections they will raise')]
        public string $objections,
        #[Schema(description: 'What would make them say yes to the next step')]
        public string $yes,
        #[Schema(description: 'A hidden piece of information that changes everything')]
        public string $secret,
    ) {
    }
}
