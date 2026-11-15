<?php

declare(strict_types=1);

namespace App\Voice;

/**
 * A line as it is said rather than written, for Pocket TTS, which reads the text as it is: numbers, prices,
 * percentages, times and ordinals in words ("32,90 €" → "trente-deux euros quatre-vingt-dix"), and the typography a
 * model writes (non-breaking hyphens and spaces, ellipses) made plain, since the voice stumbles on them.
 */
final class SpokenText
{
    private const array WORDS = [
        'fr' => ['euro' => 'euros', 'dollar' => 'dollars', 'percent' => 'pour cent', 'hour' => 'heures', 'oneHour' => 'une heure', 'point' => 'virgule', 'thousand' => 'mille', 'million' => 'millions', 'and' => 'et', 'am' => 'du matin', 'pm' => "de l'après-midi"],
        'en' => ['euro' => 'euros', 'dollar' => 'dollars', 'percent' => 'percent', 'hour' => "o'clock", 'oneHour' => "one o'clock", 'point' => 'point', 'thousand' => 'thousand', 'million' => 'million', 'and' => 'and', 'am' => 'a m', 'pm' => 'p m'],
    ];

    private readonly \NumberFormatter $cardinal;
    private readonly string $lang;

    public function __construct(string $language)
    {
        $this->lang = 'fr' === $language ? 'fr' : 'en';
        $this->cardinal = new \NumberFormatter($this->lang, \NumberFormatter::SPELLOUT);
    }

    public function of(string $text): string
    {
        $w = self::WORDS[$this->lang];
        // Typography: hyphens and spaces of every kind made plain, an ellipsis a pause.
        $text = (string) preg_replace(['/[\x{2010}\x{2011}\x{2012}]/u', '/[\x{00A0}\x{202F}\x{2009}]/u', '/\s*(…|\.\.\.)\s*(?=\p{Ll})/u', '/…|\.{3}/u', '/[«»“”]/u'], ['-', ' ', ', ', '.', '"'], $text);
        // Numbers grouped by thousands ("40 000" in French, "1,000" in English): one number.
        $text = (string) preg_replace_callback('fr' === $this->lang ? '/\b\d{1,3}(?: \d{3})+\b/' : '/\b\d{1,3}(?:,\d{3})+(?!\d)/', static fn (array $m): string => str_replace([' ', ','], '', $m[0]), $text);
        // Prices: "32,90 €", "€30", "$1.5", "30 k€", "2 M€".
        $amount = '(\d+(?:[.,]\d+)?)';
        $text = (string) preg_replace_callback('/(?:([€$])\s?'.$amount.'\s?([kKM])?|'.$amount.'\s?([kKM])?\s?(€|\$|euros?\b|dollars?\b))/u', function (array $m) use ($w): string {
            // Either the symbol first (groups 1 to 3) or the amount first (groups 4 to 6).
            $symbol = ($m[1] ?? '').($m[6] ?? '');
            $value = ($m[2] ?? '').($m[4] ?? '');
            $scale = ($m[3] ?? '').($m[5] ?? '');
            $unit = str_starts_with($symbol, '$') || str_starts_with($symbol, 'dollar') ? $w['dollar'] : $w['euro'];
            if ('' !== $scale) {
                return $this->number($value).' '.('k' === strtolower($scale) ? $w['thousand'] : $w['million']).('fr' === $this->lang && 'k' !== strtolower($scale) ? " d'" : ' ').$unit;
            }
            [$whole, $cents] = array_pad(preg_split('/[.,]/', $value) ?: [], 2, '');
            // One euro, one dollar.
            $said = $this->cardinal($whole).' '.(1 === (int) $whole ? rtrim($unit, 's') : $unit);

            return '' !== $cents && 0 !== (int) $cents ? $said.' '.$this->cardinal(str_pad($cents, 2, '0')) : $said;
        }, $text);
        // Percentages.
        $text = (string) preg_replace_callback('/'.$amount.'\s?%/', fn (array $m): string => $this->number($m[1]).' '.$w['percent'], $text);
        // Times: "14h30", "14 h", "2:30 pm", "11am".
        $text = (string) preg_replace_callback('/\b(\d{1,2})\s?h(?:\s?(\d{2}))?\b/', fn (array $m): string => ('1' === $m[1] ? $w['oneHour'] : $this->cardinal($m[1]).' '.$w['hour']).('' !== ($m[2] ?? '') && '00' !== $m[2] ? ' '.$this->cardinal($m[2]) : ''), $text);
        $text = (string) preg_replace_callback('/\b(\d{1,2})(?::(\d{2}))?\s?([ap])\.?m\.?(?!\w)/i', fn (array $m): string => $this->cardinal($m[1]).('' !== $m[2] && '00' !== $m[2] ? ' '.$this->cardinal($m[2]) : '').' '.$w['p' === strtolower($m[3]) ? 'pm' : 'am'], $text);
        // Ordinals: "1er", "2e", "3ème"; "1st", "2nd", "3rd", "4th".
        $text = (string) preg_replace_callback('/\b(\d+)(er|re|ère|e|ème|st|nd|rd|th)\b/u', fn (array $m): string => $this->ordinal($m[1], $m[2]), $text);
        // Any number left.
        $text = (string) preg_replace_callback('/\d+(?:[.,]\d+)?/', fn (array $m): string => $this->number($m[0]), $text);

        return trim((string) preg_replace('/ {2,}/', ' ', str_replace('&', ' '.$w['and'].' ', $text)));
    }

    private function number(string $value): string
    {
        [$whole, $decimals] = array_pad(preg_split('/[.,]/', $value) ?: [], 2, '');

        if ('' === $decimals) {
            return $this->cardinal($whole);
        }
        // "2,5" → "deux virgule cinq", "1,05" → "un virgule zéro cinq".
        $said = str_starts_with($decimals, '0') ? implode(' ', array_map($this->cardinal(...), str_split($decimals))) : $this->cardinal($decimals);

        return $this->cardinal($whole).' '.self::WORDS[$this->lang]['point'].' '.$said;
    }

    private function cardinal(string $digits): string
    {
        return (string) $this->cardinal->format((int) $digits);
    }

    private function ordinal(string $digits, string $suffix): string
    {
        $formatter = new \NumberFormatter($this->lang, \NumberFormatter::SPELLOUT);
        $formatter->setTextAttribute(\NumberFormatter::DEFAULT_RULESET, match (true) {
            'en' === $this->lang => '%spellout-ordinal',
            're' === $suffix => '%spellout-ordinal-feminine',
            default => '%spellout-ordinal-masculine',
        });

        return (string) $formatter->format((int) $digits);
    }
}
