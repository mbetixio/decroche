<?php

namespace App\Game;

use App\Entity\Training;

/**
 * Le côté jeu : chaque appel débriefé rapporte de l'expérience (sa note, plus un bonus par objectif atteint), qui fait
 * monter de niveau ; la difficulté des situations suit.
 */
final class Level
{
    /** Le seuil d'expérience de chaque niveau, et son nom. */
    public const LEVELS = [
        1 => [0, 'Stagiaire'],
        2 => [150, 'Junior'],
        3 => [400, 'Confirmé'],
        4 => [800, 'Senior'],
        5 => [1400, 'Expert'],
        6 => [2200, 'Chasseur d\'élite'],
        7 => [3300, 'Légende'],
    ];

    public static function xp(Training $training): int
    {
        // Une reprise s'entraîne sur un moment déjà joué : elle ne rapporte pas d'XP.
        if (Training::DONE !== $training->status || null === $training->score || null !== $training->replayOf) {
            return 0;
        }
        $bonus = array_sum(array_map(static fn (array $o): int => match ($o['status'] ?? '') { 'atteint' => 10, 'partiel' => 4, default => 0 }, $training->debrief['objectives'] ?? []));

        return $training->score + $bonus;
    }

    /**
     * @return array{number: int, name: string, xp: int, from: int, to: ?int, pct: int, next: ?string}
     */
    public static function of(int $xp): array
    {
        $number = 1;
        foreach (self::LEVELS as $n => [$threshold]) {
            if ($xp >= $threshold) {
                $number = $n;
            }
        }
        $from = self::LEVELS[$number][0];
        $to = self::LEVELS[$number + 1][0] ?? null;

        return [
            'number' => $number,
            'name' => self::LEVELS[$number][1],
            'xp' => $xp,
            'from' => $from,
            'to' => $to,
            'pct' => null === $to ? 100 : (int) floor(($xp - $from) / max(1, $to - $from) * 100),
            'next' => null === $to ? null : self::LEVELS[$number + 1][1],
        ];
    }

    /**
     * Le nombre de jours d'affilée (jusqu'à aujourd'hui ou hier) avec au moins un appel débriefé.
     *
     * @param list<Training> $trainings
     */
    public static function streak(array $trainings, \DateTimeImmutable $today): int
    {
        $days = [];
        foreach ($trainings as $t) {
            if (Training::DONE === $t->status) {
                $days[$t->startedAt->format('Y-m-d')] = true;
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
