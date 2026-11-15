<?php

declare(strict_types=1);

namespace App\Command;

use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the instance's language ("fr", "en") once chosen, at the first step of the onboarding, and fails until then:
 * the container starts the Pocket TTS server of that language only, while the rest of the onboarding goes on, so the
 * voices are ready for the first call.
 */
#[AsCommand(name: 'app:language', description: 'Prints the instance\'s language once chosen in the onboarding.')]
final readonly class LanguageCommand
{
    public function __construct(
        private CompanyRepository $companies,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $company = $this->companies->get();
        if (!$company->hasReached(OnboardingStep::Account)) {
            return Command::FAILURE;
        }
        $output->writeln($company->language->value);

        return Command::SUCCESS;
    }
}
