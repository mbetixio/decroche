<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Call\CallManager;
use App\Call\Coach\Debrief;
use App\Entity\Call;
use App\Enum\CallStatus;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Twig\Environment;

/**
 * The debrief as an MCP App, shown right in the conversation: the score, the company's criteria, the hidden brief
 * revealed with what was uncovered or missed, the best and worst lines, three tips.
 */
#[AsMcpApp(
    uri: 'ui://practicall/debrief',
    name: 'debrief',
    title: 'Show the debrief',
    description: 'Shows the graded debrief of a call (the latest by default), whether it was played here or out loud through a call link: the score out of 100, the company\'s criteria, the hidden brief revealed with what was uncovered or missed, the best and worst lines, three tips. If the call has just stopped, the debrief is written first (about thirty seconds).',
    template: 'mcp/debrief.html.twig',
    prefersBorder: false,
)]
final readonly class DebriefApp
{
    public function __construct(
        private CurrentMember $me,
        private CallManager $calls,
        private Environment $twig,
    ) {
    }

    /**
     * The debrief's summary for the model, and its HTML for the app (only plain data leaves the server: never the
     * entities themselves).
     *
     * @param int $call the call's number; 0 for the latest
     *
     * @return array<string, mixed>
     */
    public function render(int $call = 0): array
    {
        $current = $this->me->call($call);
        if (CallStatus::Debriefing === $current->status) {
            $this->calls->finish($current);
        }
        $html = $this->twig->render('mcp/_debrief.html.twig', [
            'call' => $current,
            'debrief' => null !== $current->debrief ? Debrief::fromArray($current->debrief) : null,
        ]);

        return ($current->isDone() ? CallTools::summary($current) : ['call' => $current->id, 'status' => $current->status->value]) + ['html' => $html];
    }
}
