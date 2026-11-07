<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * How hard the persona is to convince. It never makes them colder: their tone does that.
 */
enum Difficulty: int implements TranslatableInterface
{
    case Easy = 1;
    case Demanding = 2;
    case Tough = 3;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.difficulty.'.$this->value, locale: $locale);
    }

    /**
     * The length of a surprise call at this difficulty, in minutes.
     */
    public function minutes(): int
    {
        return match ($this) {
            self::Easy => 8,
            self::Demanding => 10,
            self::Tough => 12,
        };
    }

    /**
     * What the persona is told about it.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Easy => 'Difficulty: you are rather open. If the seller is genuinely interested in you, you gladly talk about your situation and let yourself be convinced by a proposal that holds up.',
            self::Demanding => 'Difficulty: you need to be convinced. You chat willingly, but the essentials (your real stakes, your budget, who decides) only come out if the seller digs with good questions; a canned pitch leaves you polite but uninterested.',
            self::Tough => 'Difficulty: you are hard to convince. You have heard many sellers, you have solid objections and little time; you only open up if the seller really listens, understands your problem and answers to the point. Demanding does not mean unpleasant: you stay human.',
        };
    }

    /**
     * The difficulties a seller of this level may draw: harder as they progress.
     *
     * @return non-empty-list<self>
     */
    public static function forLevel(int $level): array
    {
        return match (true) {
            $level <= 1 => [self::Easy, self::Easy, self::Demanding],
            $level <= 3 => [self::Easy, self::Demanding, self::Demanding, self::Tough],
            default => [self::Demanding, self::Tough, self::Tough],
        };
    }
}
