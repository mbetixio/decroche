<?php

declare(strict_types=1);

namespace App\Agent\Coach;

use App\Agent\AgentContext;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * What the client did during the call with their own tools: what they looked up, and the meetings they booked.
 */
#[AsTool('client_actions', 'What the client did during the call: the files they looked up and the meetings they booked in their calendar. A booked meeting proves a dated next step.')]
final readonly class ClientActions
{
    public function __construct(
        private AgentContext $context,
    ) {
    }

    /**
     * @param bool $meetingsOnly only the meetings booked
     *
     * @return array<string, mixed>
     */
    public function __invoke(bool $meetingsOnly = false): array
    {
        $call = $this->context->call();
        if ($meetingsOnly) {
            return ['meetings_booked' => $call->bookings()];
        }

        return [
            'meetings_booked' => $call->bookings(),
            'looked_up' => array_values(array_map(static fn (array $a): string => $a['tool'].': '.$a['summary'], array_filter($call->actions(), static fn (array $a): bool => 'book_meeting' !== $a['tool']))),
        ];
    }
}
