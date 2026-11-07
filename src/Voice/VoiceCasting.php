<?php

declare(strict_types=1);

namespace App\Voice;

use App\Entity\Scenario;

/**
 * Casts the persona's voice: their gender (given, or guessed from their first name), the accent of the situation, and
 * their name to break ties, so a persona always keeps the same voice; their tone sets its rate and pitch.
 */
final class VoiceCasting
{
    /**
     * French voices by accent and gender. Only voices that always read French: Edge's "Multilingual" voices guess the
     * language and read short lines and proper names in English, with no way to force French.
     */
    public const array VOICES = [
        'fr-FR' => ['f' => ['fr-FR-DeniseNeural'], 'm' => ['fr-FR-HenriNeural']],
        'fr-CA' => ['f' => ['fr-CA-SylvieNeural'], 'm' => ['fr-CA-AntoineNeural', 'fr-CA-JeanNeural', 'fr-CA-ThierryNeural']],
    ];

    /** Male first names ending like female ones. */
    private const string MALE_E = '/^(pierre|philippe|st[ée]phane|j[ée]r[ôo]me|antoine|maxime|guillaume|alexandre|christophe|[ée]tienne|th[ée]ophile|r[ée]mi|jules|georges|baptiste|hugues|serge|yves|andr[ée]|claude|dominique|sacha|luca|jean-.+)$/u';

    public function voice(Scenario $scenario): string
    {
        $persona = $scenario->persona();
        $first = mb_strtolower($persona->firstName());
        $gender = $persona->gender
            ?? (preg_match('/(e|a|ie|ine|elle)$/u', $first) && !preg_match(self::MALE_E, $first) ? 'f' : 'm');
        $accent = 'quebec' === $scenario->situation()?->key ? 'fr-CA' : 'fr-FR';
        $voices = self::VOICES[$accent]['f' === $gender ? 'f' : 'm'];

        return $voices[crc32($persona->name) % \count($voices)];
    }

    public function prosody(Scenario $scenario): Prosody
    {
        $tone = $scenario->persona()->tone;

        return null !== $tone ? new Prosody($tone->rate(), $tone->pitch()) : new Prosody();
    }
}
