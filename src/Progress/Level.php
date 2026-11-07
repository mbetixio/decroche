<?php

declare(strict_types=1);

namespace App\Progress;

/**
 * A member's level, from their experience points (from "Intern" to "Legend"; names are translated, "level.1"…).
 */
final readonly class Level
{
    /** The XP that reaches each level, by number. */
    public const array LEVELS = [1 => 0, 2 => 150, 3 => 400, 4 => 800, 5 => 1400, 6 => 2200, 7 => 3300];

    public function __construct(
        public int $number,
        /** The translation key of its name ("level.3"). */
        public string $name,
        public int $xp,
        /** The XP that reached this level. */
        public int $from,
        /** The XP that reaches the next one; null at the top. */
        public ?int $to,
        /** The translation key of the next level's name; null at the top. */
        public ?string $next,
    ) {
    }

    public static function of(int $xp): self
    {
        $number = 1;
        foreach (self::LEVELS as $n => $threshold) {
            if ($xp >= $threshold) {
                $number = $n;
            }
        }
        $next = self::LEVELS[$number + 1] ?? null;

        return new self($number, 'level.'.$number, $xp, self::LEVELS[$number], $next, null !== $next ? 'level.'.($number + 1) : null);
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
