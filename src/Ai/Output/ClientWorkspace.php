<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * What the persona has at hand during the call, as their own systems hold it: their calendar and a few sources that
 * fit their job and the situation. They look things up there with their tools; the seller never sees it.
 */
final class ClientWorkspace extends Output
{
    /**
     * @param list<CalendarSlot>    $calendar
     * @param list<WorkspaceSource> $sources
     */
    public function __construct(
        #[Schema(description: 'Their busy slots over the next ten working days, 8 to 20 of them, during office hours')]
        public array $calendar,
        #[Schema(description: '2 to 4 sources that fit their job and the situation', minItems: 1, maxItems: 4)]
        public array $sources,
    ) {
    }
}
