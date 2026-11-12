<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * A surprise phone call written from a drawn situation (App\Scenario\ScenarioWriter::surprise()).
 */
final class CallScenario extends Output
{
    /**
     * @param list<string> $objectives
     */
    public function __construct(
        #[Schema(description: 'A short, telling title, as the seller sees it')]
        public string $title,
        #[Schema(description: 'What the seller knows before the call, addressing them as you, 2 to 4 sentences')]
        public string $context,
        #[Schema(description: 'The document the seller has in front of them, written in full, 5 to 15 lines')]
        public string $material,
        #[Schema(description: 'The goal of the call in one sentence')]
        public string $goal,
        #[Schema(description: 'Three concrete, checkable objectives for this call', minItems: 1, maxItems: 3)]
        public array $objectives,
        public PersonaSketch $persona,
        #[Schema(description: 'The persona\'s first line on the phone')]
        public string $opening,
        public HiddenBrief $brief,
        public Workspace $workspace,
    ) {
    }
}
