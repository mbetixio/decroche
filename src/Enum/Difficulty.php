<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How hard the persona is to convince. It never makes them colder: their tone does that.
 */
enum Difficulty: int
{
    case Easy = 1;
    case Demanding = 2;
    case Tough = 3;

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Accessible',
            self::Demanding => 'Exigeant',
            self::Tough => 'Coriace',
        };
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
            self::Easy => 'Côté difficulté : tu es plutôt ouvert. Si le commercial s\'intéresse vraiment à toi, tu parles volontiers de ta situation et tu te laisses convaincre par une proposition qui tient la route.',
            self::Demanding => 'Côté difficulté : tu as besoin d\'être convaincu. Tu discutes volontiers, mais l\'essentiel (tes vrais enjeux, ton budget, qui décide) ne sort que si le commercial creuse avec de bonnes questions ; un discours tout fait te laisse poli mais pas intéressé.',
            self::Tough => 'Côté difficulté : tu es difficile à convaincre. Tu as déjà entendu beaucoup de commerciaux, tu as des objections solides et peu de temps ; tu ne t\'ouvres que si le commercial t\'écoute vraiment, comprend ton problème et répond juste. Exigeant ne veut pas dire désagréable : tu restes humain.',
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
