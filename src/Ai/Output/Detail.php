<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * One field of a record, as any business system shows it: a name and its value.
 */
final class Detail extends Output
{
    public function __construct(
        #[Schema(description: 'The field\'s name, e.g. Status, Quantity, Amount, Supplier, Priority')]
        public string $name,
        #[Schema(description: 'Its value, precise: figures, references, dates, names')]
        public string $value,
    ) {
    }
}
