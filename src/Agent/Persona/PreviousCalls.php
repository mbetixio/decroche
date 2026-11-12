<?php

declare(strict_types=1);

namespace App\Agent\Persona;

use App\Agent\AgentContext;
use App\Entity\Call;
use App\Model\Line;
use App\Repository\CallRepository;
use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The persona remembers their earlier calls with this seller: when, how it ended, and what the seller said last.
 */
#[AsTool('previous_calls', 'Remember your earlier calls with this seller: when they were, how they ended and what the seller said or promised at the end.')]
final readonly class PreviousCalls
{
    public function __construct(
        private AgentContext $context,
        private CallRepository $calls,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param int $limit how many of the latest calls
     *
     * @return array<string, mixed>
     */
    public function __invoke(int $limit = 3): array
    {
        $call = $this->context->call();
        $name = $call->scenario->persona()->name;
        $earlier = array_values(array_filter(
            $this->calls->history($call->member, 50),
            static fn (Call $c): bool => $c !== $call && $c->startedAt < $call->startedAt && $c->scenario->persona()->name === $name,
        ));
        $call->act('previous_calls', (string) \count($earlier), $this->clock->now(), ['count' => \count($earlier)]);
        if ([] === $earlier) {
            return ['calls' => [], 'note' => 'You have never spoken with this seller before.'];
        }

        return ['calls' => array_map(static fn (Call $c): array => [
            'date' => $c->startedAt->format('Y-m-d'),
            'ended' => $c->endedBy->value ?? 'live',
            'seller_said_last' => array_map(static fn (Line $l): string => $l->text, \array_slice(array_values(array_filter($c->lines(), static fn (Line $l): bool => $l->isSeller())), -3)),
            'meetings_booked' => $c->bookings(),
        ], \array_slice($earlier, 0, max(1, min(10, $limit))))];
    }
}
