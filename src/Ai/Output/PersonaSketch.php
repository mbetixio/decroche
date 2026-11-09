<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The person the model will play on the phone.
 */
final class PersonaSketch extends Output
{
    public function __construct(
        #[Schema(description: 'First name and last name')]
        public string $name,
        #[Schema(enum: ['f', 'm'])]
        public string $gender,
        #[Schema(description: 'Job title')]
        public string $role,
        #[Schema(description: 'Company name, sector, size, city')]
        public string $company,
        #[Schema(description: 'Their character on the phone, how they speak, what matters to them, a human trait')]
        public string $personality,
    ) {
    }
}
