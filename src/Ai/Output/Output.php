<?php

declare(strict_types=1);

namespace App\Ai\Output;

/**
 * An answer the model writes into a fixed shape: Symfony AI turns the class into a strict JSON schema (sent as the
 * request's response_format), then the model's JSON back into the object. The rest of the application keeps reading
 * plain arrays, through toArray(), with the same tolerant readers as before.
 */
abstract class Output
{
    /**
     * The answer as an array, nested objects included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        foreach (new \ReflectionObject($this)->getProperties() as $property) {
            $data[$property->getName()] = self::plain($property->getValue($this));
        }

        return $data;
    }

    /**
     * A value with its nested answers turned into arrays.
     */
    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self => $value->toArray(),
            \is_array($value) => array_map(self::plain(...), $value),
            default => $value,
        };
    }
}
