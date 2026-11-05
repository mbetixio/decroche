<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The sections of a persona's hidden brief, in the order the debrief reveals them.
 */
enum BriefSection: string
{
    case Budget = 'budget';
    case Pain = 'pain';
    case Decision = 'decision';
    case Objections = 'objections';
    case Yes = 'yes';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::Budget => 'Budget réel',
            self::Pain => 'Vraie douleur',
            self::Decision => 'Décideur et processus',
            self::Objections => 'Objections',
            self::Yes => 'Ce qui le ferait dire oui',
            self::Secret => 'Ce qu\'il ne dit pas d\'emblée',
        };
    }

    /**
     * What the scenario writer is asked to write for it.
     */
    public function prompt(): string
    {
        return match ($this) {
            self::Budget => 'le budget réel et ses contraintes',
            self::Pain => 'la vraie douleur, qu\'il ne dit pas d\'emblée',
            self::Decision => 'qui décide vraiment, le processus, le calendrier',
            self::Objections => '2 ou 3 objections qu\'il sortira',
            self::Yes => 'ce qui le ferait dire oui à la prochaine étape',
            self::Secret => 'une information cachée qui change la donne',
        };
    }
}
