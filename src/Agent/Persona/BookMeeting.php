<?php

declare(strict_types=1);

namespace App\Agent\Persona;

use App\Agent\AgentContext;
use App\Model\Workspace;
use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The persona puts a meeting the seller proposed in their calendar, if they are free then. The booking is the proof,
 * for the coach, that a dated next step was obtained.
 */
#[AsTool('book_meeting', 'Put in your calendar a meeting the seller proposed and you accept: the day, the hour and what it is about. It fails if you are busy then.')]
final readonly class BookMeeting
{
    public function __construct(
        private AgentContext $context,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string $date    the day, as YYYY-MM-DD
     * @param string $time    the hour, as HH:MM
     * @param int    $minutes how long it lasts
     * @param string $subject what the meeting is about
     *
     * @return array<string, mixed>
     */
    public function __invoke(string $date, string $time, int $minutes = 30, string $subject = ''): array
    {
        $call = $this->context->call();
        $day = CheckCalendar::day($date, $this->clock->now());
        $start = null !== $day ? Workspace::moment($day->format('Y-m-d').' '.trim($time)) : null;
        if (null === $start) {
            return ['booked' => false, 'error' => 'Give the day as YYYY-MM-DD and the hour as HH:MM.'];
        }
        $minutes = max(15, min(240, $minutes));
        $workspace = $call->scenario->workspace();
        if ($start < $this->clock->now() || !Workspace::isOpen($start, $minutes)) {
            return ['booked' => false, 'reason' => 'That is not a working slot (past, weekend or outside office hours).', 'free_hours' => $workspace->free($start)];
        }
        if ($workspace->overlaps($start, $minutes)) {
            return ['booked' => false, 'reason' => 'You are busy then.', 'free_hours' => $workspace->free($start)];
        }
        $call->act('book_meeting', $start->format('Y-m-d H:i').' · '.$subject, $this->clock->now(), ['date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'), 'minutes' => $minutes, 'subject' => $subject]);

        return ['booked' => true, 'when' => $start->format('l Y-m-d H:i'), 'minutes' => $minutes];
    }
}
