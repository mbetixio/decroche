<?php

declare(strict_types=1);

namespace App\Call\Persona;

use App\Enum\BriefSection;

/**
 * The persona's complete reply, once streamed.
 */
final readonly class Reply
{
    /**
     * @param list<BriefSection> $revealed the brief sections it gave away
     */
    public function __construct(
        public string $text,
        public array $revealed,
        public bool $hungUp,
    ) {
    }
}
