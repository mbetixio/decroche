<?php

declare(strict_types=1);

namespace App\Voice;

/**
 * How a voice speaks: its rate ("+12%") and pitch ("+2Hz").
 */
final readonly class Prosody
{
    public function __construct(
        public string $rate = '+12%',
        public string $pitch = '+0Hz',
    ) {
        if (!preg_match('/^[+-]\d{1,2}%$/', $rate) || !preg_match('/^[+-]\d{1,2}Hz$/', $pitch)) {
            throw new \InvalidArgumentException(\sprintf('Invalid prosody "%s" / "%s".', $rate, $pitch));
        }
    }
}
