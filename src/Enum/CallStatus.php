<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The life of a practice call: live, hung up and waiting for its debrief, debriefed, or abandoned.
 */
enum CallStatus: string implements TranslatableInterface
{
    case Live = 'live';
    case Debriefing = 'debriefing';
    case Done = 'done';
    case Abandoned = 'abandoned';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.call_status.'.$this->value, locale: $locale);
    }
}
