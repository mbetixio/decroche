<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The sections of a persona's hidden brief, in the order the debrief reveals them.
 */
enum BriefSection: string implements TranslatableInterface
{
    case Budget = 'budget';
    case Pain = 'pain';
    case Decision = 'decision';
    case Objections = 'objections';
    case Yes = 'yes';
    case Secret = 'secret';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.brief_section.'.$this->value, locale: $locale);
    }

    /**
     * Its name in English, for the model's instructions.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Budget => 'Real budget',
            self::Pain => 'Real pain',
            self::Decision => 'Decision maker and process',
            self::Objections => 'Objections',
            self::Yes => 'What would make them say yes',
            self::Secret => 'What they do not say at first',
        };
    }

    /**
     * What the scenario writer is asked to write for it.
     */
    public function prompt(): string
    {
        return match ($this) {
            self::Budget => 'the real budget and its constraints',
            self::Pain => 'the real pain, which they do not tell at first',
            self::Decision => 'who really decides, the process, the timeline',
            self::Objections => '2 or 3 objections they will raise',
            self::Yes => 'what would make them say yes to the next step',
            self::Secret => 'a hidden piece of information that changes everything',
        };
    }
}
