<?php

declare(strict_types=1);

namespace App\Agent\Persona;

use App\Agent\AgentContext;
use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The persona looks something up in one of their own files (tickets, stock, orders, suppliers… whatever their
 * workspace holds), as a real client would while on the phone.
 */
#[AsTool('look_up', 'Look something up in one of your own files, as you would on your screen during the call. Give the key of the file and what you are looking for (a reference, a name, a product, a word); leave the query empty to see its latest records.')]
final readonly class LookUp
{
    public function __construct(
        private AgentContext $context,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string $source the key of one of your files
     * @param string $query  what you are looking for; empty for the latest records
     *
     * @return array<string, mixed>
     */
    public function __invoke(string $source, string $query = ''): array
    {
        $call = $this->context->call();
        $workspace = $call->scenario->workspace();
        $sources = $workspace->sources();
        if (!isset($sources[$source])) {
            return ['error' => 'No such file.', 'your_files' => array_map(static fn (array $s): string => $s['label'], $sources)];
        }
        $found = $workspace->search($source, $query);
        $call->act('look_up', $sources[$source]['label'].('' !== trim($query) ? ': '.trim($query) : ''), $this->clock->now(), ['source' => $source, 'query' => $query, 'found' => \count($found)]);

        return ['file' => $sources[$source]['label'], 'records' => $found, 'note' => [] === $found ? 'Nothing matches: say so, or look for something else.' : 'Use what helps, in your own words; never read it out.'];
    }
}
