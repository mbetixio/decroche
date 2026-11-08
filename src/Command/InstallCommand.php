<?php

declare(strict_types=1);

namespace App\Command;

use App\Company\DemoCompany;
use App\Company\TeamManager;
use App\Enum\Language;
use App\Enum\MemberRole;
use App\Repository\CompanyRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Installs the instance: the database schema, then either nothing more (the first visit opens the onboarding), or with
 * --demo the example company, its library and a first manager, ready to play.
 */
#[AsCommand(name: 'app:install', description: 'Creates the database; with --demo, loads the example company and a manager.')]
final readonly class InstallCommand
{
    public function __construct(
        private KernelInterface $kernel,
        private CompanyRepository $companies,
        private MemberRepository $members,
        private DemoCompany $demo,
        private TeamManager $team,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Load the example company, its library and a manager, skipping the onboarding')] bool $demo = false,
        #[Option(description: 'The working language of the example company (en or fr)')] string $language = 'en',
        #[Option(description: 'The demo manager\'s e-mail address')] string $email = 'manager@practicall.test',
        #[Option(description: 'The demo manager\'s password (generated when omitted)')] ?string $password = null,
    ): int {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $io->section('Database');
        $application->doRun(new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true, '--allow-no-migration' => true]), $io);

        if (!$demo) {
            $io->success('Installed. Open the instance in a browser: the onboarding sets it up step by step.');

            return Command::SUCCESS;
        }
        if ($this->members->hasAny()) {
            $io->warning('This instance already has members: the example is not loaded again.');

            return Command::SUCCESS;
        }

        $io->section('Example company');
        $company = $this->companies->get();
        $company->language = Language::tryFrom($language) ?? Language::English;
        $this->demo->apply($company);
        $scenarios = $this->demo->loadLibrary($company->language);
        $password ??= bin2hex(random_bytes(6));
        $this->team->create('Demo Manager', $email, $password, MemberRole::Manager, 'Sales manager of the example company, I train the team and sell myself.');
        $company->completeOnboarding($this->clock->now());
        $this->em->flush();

        $io->definitionList(
            ['Company' => $company->name.' ('.$company->language->englishName().')'],
            ['Situations' => (string) \count($company->situations())],
            ['Library' => $scenarios.' scenarios'],
            ['Manager' => $email],
            ['Password' => $password],
        );
        $io->success('Ready: sign in and start a call.');

        return Command::SUCCESS;
    }
}
