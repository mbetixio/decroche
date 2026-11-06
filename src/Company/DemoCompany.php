<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\Company;
use App\Model\Situation;
use App\Repository\ScenarioRepository;
use App\Scenario\ScenarioFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The example company (config/sparring/demo.yaml) and its library (library.yaml): to try Sparring at once, without
 * analysing a website.
 */
final readonly class DemoCompany
{
    public function __construct(
        private ScenarioRepository $scenarios,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/config/sparring')]
        private string $directory,
    ) {
    }

    /**
     * Fills the company in with the example's profile, offer sheet, sectors and situations.
     */
    public function apply(Company $company): void
    {
        /** @var array{name: string, website: string, about: string, offer: string, sectors: list<string>, situations: array<string, array<mixed>>} $demo */
        $demo = Yaml::parseFile($this->directory.'/demo.yaml');
        $company->name = $demo['name'];
        $company->website = $demo['website'];
        $company->about = $demo['about'];
        $company->offer = $demo['offer'];
        $company->setSectors($demo['sectors']);
        $situations = [];
        foreach ($demo['situations'] as $key => $data) {
            if (null !== $situation = Situation::fromArray($key, $data)) {
                $situations[] = $situation;
            }
        }
        $company->setSituations($situations);
        $company->analyzedAt = $this->clock->now();
    }

    /**
     * Adds the example library to the scenarios (an entry already there, by title, is updated). Returns how many were
     * added.
     */
    public function loadLibrary(): int
    {
        /** @var list<array<mixed>> $entries */
        $entries = Yaml::parseFile($this->directory.'/library.yaml');
        $added = 0;
        foreach ($entries as $entry) {
            $fresh = ScenarioFactory::fromArray($entry, null, $this->clock->now());
            $existing = $this->scenarios->findOneByTitle($fresh->title);
            if (null === $existing) {
                $this->scenarios->save($fresh);
                ++$added;
                continue;
            }
            $existing->context = $fresh->context;
            $existing->goal = $fresh->goal;
            $existing->opening = $fresh->opening;
            $existing->setPersona($fresh->persona());
            $existing->setBrief($fresh->briefArray());
            $this->scenarios->save($existing);
        }

        return $added;
    }
}
