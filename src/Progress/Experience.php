<?php

declare(strict_types=1);

namespace App\Progress;

use App\Call\Coach\Debrief;
use App\Entity\Call;

/**
 * Experience points and streaks.
 */
final readonly class Experience
{
    /**
     * The XP a call brings: its score, plus a bonus per objective reached (10) or partly reached (4). A replay of a
     * moment trains on something already played: it brings none.
     */
    public function of(Call $call): int
    {
        if (!$call->isDone() || null === $call->score || $call->isReplay() || null === $call->debrief) {
            return 0;
        }
        $bonus = array_sum(array_map(static fn (array $o): int => $o['status']->bonus(), Debrief::fromArray($call->debrief)->objectives));

        return $call->score + $bonus;
    }

    /**
     * Consecutive days with at least one debriefed call, up to today (or yesterday, if today is still to be played).
     *
     * @param iterable<Call> $calls
     */
    public function streak(iterable $calls, \DateTimeImmutable $today): int
    {
        $days = [];
        foreach ($calls as $call) {
            if ($call->isDone()) {
                $days[$call->startedAt->format('Y-m-d')] = true;
            }
        }
        $day = $today->setTime(0, 0);
        if (!isset($days[$day->format('Y-m-d')])) {
            $day = $day->modify('-1 day');
        }
        $streak = 0;
        while (isset($days[$day->format('Y-m-d')])) {
            ++$streak;
            $day = $day->modify('-1 day');
        }

        return $streak;
    }
}
