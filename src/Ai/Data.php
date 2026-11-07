<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Reads the loose data a model writes (or that was stored as JSON) into exact types: a missing or malformed value
 * becomes empty rather than breaking the page.
 */
final class Data
{
    public static function string(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) round((float) $value) : $default;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function map(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * The arrays of a list (each a row of fields), the rest dropped.
     *
     * @return list<array<array-key, mixed>>
     */
    public static function rows(mixed $value): array
    {
        return array_values(array_filter(self::map($value), \is_array(...)));
    }

    /**
     * The non-empty strings of a list.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_filter(array_map(self::string(...), self::map($value)), static fn (string $s): bool => '' !== $s));
    }
}
