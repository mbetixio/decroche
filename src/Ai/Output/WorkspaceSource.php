<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * One of the persona's systems or files, whatever their business: support tickets, stock levels, suppliers, orders,
 * invoices, e-mails, CRM notes, project status, contracts….
 */
final class WorkspaceSource extends Output
{
    /**
     * @param list<WorkspaceEntry> $entries
     */
    public function __construct(
        #[Schema(description: 'A short identifier in snake_case, e.g. tickets, stock, suppliers')]
        public string $key,
        #[Schema(description: 'Its name, as the persona calls it, in the working language')]
        public string $label,
        #[Schema(description: 'What it holds, in one sentence')]
        public string $description,
        #[Schema(description: '3 to 8 records', minItems: 1)]
        public array $entries,
    ) {
    }
}
