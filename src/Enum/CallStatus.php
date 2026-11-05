<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The life of a practice call: live, hung up and waiting for its debrief, debriefed, or abandoned.
 */
enum CallStatus: string
{
    case Live = 'live';
    case Debriefing = 'debriefing';
    case Done = 'done';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'En cours',
            self::Debriefing => 'À analyser',
            self::Done => 'Débriefé',
            self::Abandoned => 'Abandonné',
        };
    }
}
