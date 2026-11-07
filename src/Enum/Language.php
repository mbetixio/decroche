<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The working language of the instance, chosen during the onboarding: the interface, the personas, the coach, the
 * analysis and the situations all speak it.
 */
enum Language: string
{
    case French = 'fr';
    case English = 'en';

    /**
     * Its name in itself, for the language picker.
     */
    public function nativeName(): string
    {
        return match ($this) {
            self::French => 'Français',
            self::English => 'English',
        };
    }

    /**
     * Its name in English, for the model's instructions.
     */
    public function englishName(): string
    {
        return match ($this) {
            self::French => 'French',
            self::English => 'English',
        };
    }

    /**
     * The locale of the browser's speech recognition.
     */
    public function speechLocale(): string
    {
        return match ($this) {
            self::French => 'fr-FR',
            self::English => 'en-US',
        };
    }

    public function dateFormat(): string
    {
        return match ($this) {
            self::French => 'd/m/Y',
            self::English => 'F j, Y',
        };
    }
}
