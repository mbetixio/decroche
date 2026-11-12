<?php

declare(strict_types=1);

namespace App\Agent\Coach;

use App\Agent\AgentContext;
use App\Call\Coach\Debrief;
use App\Entity\Call;
use App\Repository\CallRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The seller's latest debriefs, for the coach to spot what keeps coming back.
 */
#[AsTool('previous_debriefs', 'The seller\'s latest graded calls before this one: their score, their weakest criteria, the lines to rework and the tips they were given. Use it to spot a mistake that keeps coming back.')]
final readonly class PreviousDebriefs
{
    public function __construct(
        private AgentContext $context,
        private CallRepository $calls,
    ) {
    }

    /**
     * @param int $limit how many of the latest debriefs
     *
     * @return list<array<string, mixed>>
     */
    public function __invoke(int $limit = 5): array
    {
        $call = $this->context->call();
        $earlier = array_filter($this->calls->history($call->member, 30), static fn (Call $c): bool => $c !== $call && null !== $c->debrief && null !== $c->score);

        return array_map(static function (Call $c): array {
            $debrief = Debrief::fromArray((array) $c->debrief);
            $criteria = $debrief->criteria;
            uasort($criteria, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);

            return [
                'date' => $c->startedAt->format('Y-m-d'),
                'call' => $c->scenario->title,
                'score' => $c->score,
                'weakest' => array_map(static fn (array $x): string => $x['label'].' '.$x['score'].'/10', \array_slice($criteria, 0, 2)),
                'to_rework' => array_column($debrief->worst, 'quote'),
                'tips' => $debrief->tips,
            ];
        }, \array_slice($earlier, 0, max(1, min(10, $limit))));
    }
}
