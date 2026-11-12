<?php

declare(strict_types=1);

namespace App\Agent\Writer;

use App\Agent\AgentContext;
use App\Entity\Scenario;
use App\Repository\ScenarioRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The seller's latest calls, for the writer to make the next one new: other people, other companies, other stakes.
 */
#[AsTool('recent_calls', 'The seller\'s latest practice calls: the situation, the person and their company. Write something new: never the same names, nor the same story.')]
final readonly class RecentCalls
{
    public function __construct(
        private AgentContext $context,
        private ScenarioRepository $scenarios,
    ) {
    }

    /**
     * Every tool declares at least one parameter: some providers refuse a tool without any.
     *
     * @param int $limit how many of the latest calls
     *
     * @return list<array{title: string, situation: string, person: string, company: string}>
     */
    public function __invoke(int $limit = 12): array
    {
        return array_map(static fn (Scenario $s): array => [
            'title' => $s->title,
            'situation' => $s->situation()->label ?? '',
            'person' => $s->persona()->name,
            'company' => $s->persona()->company,
        ], $this->scenarios->recentSurpriseCalls($this->context->member(), max(1, min(30, $limit))));
    }
}
