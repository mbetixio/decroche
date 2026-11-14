<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The rehearsal of a real meeting, from a prospect file or a call transcript (App\Scenario\ScenarioWriter::prepare()).
 */
final class MeetingScenario extends Output
{
    public function __construct(
        #[Schema(description: 'A short, telling title')]
        public string $title,
        #[Schema(enum: ['discovery', 'objection', 'negotiation', 'unhappy_client', 'rushed', 'closing'])]
        public string $type,
        #[Schema(description: 'What the seller knows going in, addressing them as you, 2 to 4 sentences')]
        public string $context,
        #[Schema(description: 'What they must obtain for the meeting to be a success, in one sentence')]
        public string $goal,
        public PersonaSketch $persona,
        #[Schema(description: 'The first sentence the client says when greeting the seller')]
        public string $opening,
        public HiddenBrief $brief,
        public ClientWorkspace $workspace,
    ) {
    }
}
