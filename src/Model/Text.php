<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Text compared loosely, the same way everywhere (a coach's quote against the transcript, a tool's search): lower case,
 * typographic apostrophes and ligatures folded, punctuation dropped.
 */
final class Text
{
    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(str_replace(['’', 'œ', 'Œ'], ["'", 'oe', 'oe'], $text))));
    }

    /**
     * The words of a text, normalized, those shorter than the given length left out.
     *
     * @return list<string>
     */
    public static function words(string $text, int $minLength = 1): array
    {
        return array_values(array_filter(explode(' ', self::normalize($text)), static fn (string $w): bool => mb_strlen($w) >= $minLength));
    }
}
