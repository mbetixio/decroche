<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Enum\Difficulty;
use App\Enum\Tone;
use App\Model\Situation;

/**
 * A surprise call drawn at random: a situation of the company's catalogue, dressed up with a sector, a company size, a
 * city, a tone for the persona and a difficulty.
 */
final readonly class Draw
{
    public function __construct(
        public Situation $situation,
        public string $sector,
        public string $size,
        public string $city,
        public Tone $tone,
        public Difficulty $difficulty,
    ) {
    }
}
