<?php

declare(strict_types=1);

namespace App\Voice;

use App\Entity\Scenario;
use App\Enum\Language;

/**
 * Casts the persona's voice: the working language, their gender (given, or guessed from a French first name), the
 * accent of the situation, and their name to break ties, so a persona always keeps the same voice; their tone sets its
 * rate and pitch (for Edge's voices). Pocket TTS speaks when its server runs, with many voices for variety; Edge's
 * voices otherwise, and for the Quebec accent.
 */
final readonly class VoiceCasting
{
    /**
     * Pocket TTS's voices by language and gender: its built-in English ones, and French ones exported from native
     * speakers' recordings (CML-TTS, under public/voices/fr/).
     */
    public const array POCKET = [
        'fr' => [
            'f' => ['fr/f-2154_2576_000020-0003', 'fr/f-7400_2928_000100-0001', 'fr/f-10087_11650_000028-0002', 'fr/f-5207_3078_000031-0002', 'fr/f-579_2548_000015-0001', 'fr/f-2465_1943_000152-0002', 'fr/f-12977_10625_000037-0001'],
            'm' => ['fr/m-9834_9697_000150-0003', 'fr/m-4482_3103_000063-0001', 'fr/m-5790_4893_000052-0001', 'fr/m-2223_1745_000009-0002', 'fr/m-1770_1028_000036-0002', 'fr/m-7142_2432_000124-0003', 'fr/m-4724_3731_000031-0001'],
        ],
        'en' => [
            'f' => ['jane', 'anna', 'eve', 'mary', 'vera', 'caro_davy', 'azelma', 'eponine', 'fantine', 'cosette'],
            'm' => ['michael', 'george', 'paul', 'charles', 'jean', 'bill_boerst', 'peter_yearsley', 'stuart_bell', 'javert'],
        ],
    ];

    /**
     * Voices by accent and gender. Only voices tied to one language: Edge's "Multilingual" voices guess the language
     * and read short lines and proper names in the wrong one, with no way to force it. The English ones are Edge's
     * conversational voices.
     */
    public const array VOICES = [
        'fr-FR' => ['f' => ['fr-FR-DeniseNeural'], 'm' => ['fr-FR-HenriNeural']],
        'fr-CA' => ['f' => ['fr-CA-SylvieNeural'], 'm' => ['fr-CA-AntoineNeural', 'fr-CA-JeanNeural', 'fr-CA-ThierryNeural']],
        'en-US' => ['f' => ['en-US-AvaNeural', 'en-US-EmmaNeural'], 'm' => ['en-US-AndrewNeural', 'en-US-BrianNeural']],
    ];

    /** Male first names ending like female ones. */
    private const string MALE_E = '/^(pierre|philippe|st[ée]phane|j[ée]r[ôo]me|antoine|maxime|guillaume|alexandre|christophe|[ée]tienne|th[ée]ophile|r[ée]mi|jules|georges|baptiste|hugues|serge|yves|andr[ée]|claude|dominique|sacha|luca|jean-.+)$/u';

    public function __construct(
        private ?PocketTtsSynthesizer $pocket = null,
        private ?EdgeTtsSynthesizer $edge = null,
    ) {
    }

    public function voice(Scenario $scenario, Language $language): string
    {
        $persona = $scenario->persona();
        $first = mb_strtolower($persona->firstName());
        $gender = $persona->gender
            ?? (preg_match('/(e|a|ie|ine|elle)$/u', $first) && !preg_match(self::MALE_E, $first) ? 'f' : 'm');
        $accent = match (true) {
            Language::English === $language => 'en-US',
            'quebec' === $scenario->situation()?->key => 'fr-CA',
            default => 'fr-FR',
        };
        $gender = 'f' === $gender ? 'f' : 'm';
        if (true === $this->pocket?->isAvailable() && !('fr-CA' === $accent && true === $this->edge?->isAvailable())) {
            $voices = self::POCKET[$language->value][$gender];

            return PocketTtsSynthesizer::PREFIX.$voices[crc32($persona->name) % \count($voices)];
        }
        $voices = self::VOICES[$accent][$gender];

        return $voices[crc32($persona->name) % \count($voices)];
    }

    public function prosody(Scenario $scenario): Prosody
    {
        $tone = $scenario->persona()->tone;

        return null !== $tone ? new Prosody($tone->rate(), $tone->pitch()) : new Prosody();
    }
}
