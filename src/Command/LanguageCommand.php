<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CompanyRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the instance's language ("fr", "en") once the onboarding is completed, and fails until then: the container
 * starts the Pocket TTS server of that language only, as soon as it is known.
 */
#[AsCommand(name: 'app:language', description: 'Prints the instance\'s language once the onboarding is completed.')]
final readonly class LanguageCommand
{
    public function __construct(
        private CompanyRepository $companies,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $company = $this->companies->get();
        if (!$company->isOnboarded()) {
            return Command::FAILURE;
        }
        $output->writeln($company->language->value);

        return Command::SUCCESS;
    }
}
