<?php

declare(strict_types=1);

namespace App\Progress;

/**
 * A member's level, from their experience points: Stagiaire to Légende.
 */
final readonly class Level
{
    /** The levels, by number: the XP that reaches them and their name. */
    public const array LEVELS = [
        1 => [0, 'Stagiaire'],
        2 => [150, 'Junior'],
        3 => [400, 'Confirmé'],
        4 => [800, 'Senior'],
        5 => [1400, 'Expert'],
        6 => [2200, 'Chasseur d\'élite'],
        7 => [3300, 'Légende'],
    ];

    public function __construct(
        public int $number,
        public string $name,
        public int $xp,
        /** The XP that reached this level. */
        public int $from,
        /** The XP that reaches the next one; null at the top. */
        public ?int $to,
        public ?string $next,
    ) {
    }

    public static function of(int $xp): self
    {
        $number = 1;
        foreach (self::LEVELS as $n => [$threshold]) {
            if ($xp >= $threshold) {
                $number = $n;
            }
        }
        $next = self::LEVELS[$number + 1] ?? null;

        return new self($number, self::LEVELS[$number][1], $xp, self::LEVELS[$number][0], $next[0] ?? null, $next[1] ?? null);
    }

    /**
     * How far the member is towards the next level, in percent.
     */
    public function percent(): int
    {
        return null === $this->to ? 100 : (int) floor(($this->xp - $this->from) / max(1, $this->to - $this->from) * 100);
    }

    /**
     * @return array{number: int, name: string, xp: int, from: int, to: ?int, pct: int, next: ?string}
     */
    public function toArray(): array
    {
        return ['number' => $this->number, 'name' => $this->name, 'xp' => $this->xp, 'from' => $this->from, 'to' => $this->to, 'pct' => $this->percent(), 'next' => $this->next];
    }
}
