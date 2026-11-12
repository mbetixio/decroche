<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * One record of a source: a ticket, a stock line, an order, an e-mail, a supplier, an invoice….
 */
final class WorkspaceEntry extends Output
{
    /**
     * @param list<Detail> $details
     */
    public function __construct(
        #[Schema(description: 'What the record is about, as its system titles it (a reference and a subject)')]
        public string $title,
        #[Schema(description: 'Its date, as YYYY-MM-DD (with HH:MM if useful); empty if none')]
        public string $when,
        #[Schema(description: '2 to 6 fields of the record')]
        public array $details,
        #[Schema(description: 'Free text of the record (the message, the comment, the history), 1 to 3 sentences; empty if none')]
        public string $note,
    ) {
    }
}
