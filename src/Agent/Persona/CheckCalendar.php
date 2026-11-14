<?php

declare(strict_types=1);

namespace App\Agent\Persona;

use App\Agent\AgentContext;
use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The persona opens their calendar: what they have on a day, and when they are free.
 */
#[AsTool('check_calendar', 'Open your calendar on a day: what you already have, and the hours you are free. Use it before agreeing to a date.')]
final readonly class CheckCalendar
{
    public function __construct(
        private AgentContext $context,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string $date the day, as YYYY-MM-DD
     *
     * @return array<string, mixed>
     */
    public function __invoke(string $date): array
    {
        $call = $this->context->call();
        $day = self::day($date, $this->clock->now());
        if (null === $day) {
            return ['error' => 'Give the day as YYYY-MM-DD.'];
        }
        $workspace = $call->scenario->workspace();
        $call->act('check_calendar', $day->format('Y-m-d'), $this->clock->now(), ['date' => $day->format('Y-m-d')]);

        return [
            'day' => $day->format('l Y-m-d'),
            'busy' => array_map(static fn (array $s): string => substr($s['when'], 11).' ('.$s['minutes'].' min): '.$s['what'], $workspace->busy($day)),
            'free_hours' => $workspace->free($day),
        ];
    }

    /**
     * A day from what the model wrote: a date, or failing that a relative day ("next tuesday"), never in the past.
     */
    public static function day(string $date, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        if (false !== $day && $day->format('Y-m-d') !== trim($date)) {
            return null;
        }
        if (false === $day) {
            try {
                $day = $now->modify(trim($date))->setTime(0, 0);
            } catch (\DateMalformedStringException) {
                return null;
            }
        }

        return $day < $now->setTime(0, 0) ? null : $day;
    }
}
