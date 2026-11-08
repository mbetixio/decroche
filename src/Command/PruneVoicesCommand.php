<?php

declare(strict_types=1);

namespace App\Command;

use App\Voice\VoiceCachePruner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the synthesized lines not heard for a while.
 */
#[AsCommand(name: 'app:prune-voices', description: 'Deletes the cached voices not heard for a number of days.')]
final readonly class PruneVoicesCommand
{
    public function __construct(
        private VoiceCachePruner $pruner,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Delete the lines not heard for this many days')] int $days = VoiceCachePruner::DAYS,
    ): int {
        $deleted = $this->pruner->prune(max(0, $days));
        $io->success(\sprintf('%d voice file(s) deleted (%.1f MB).', $deleted['files'], $deleted['bytes'] / 1048576));

        return Command::SUCCESS;
    }
}
