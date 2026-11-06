<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Model\Situation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The common canvas (config/sparring/situations.yaml): the call situations nearly every B2B sales team meets, which
 * the analysis of a company adapts to its business, and the random dressing of a surprise call.
 */
final class SituationCanvas
{
    /** @var array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>}|null */
    private ?array $data = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/sparring/situations.yaml')]
        private readonly string $file,
    ) {
    }

    /**
     * @return array<string, Situation>
     */
    public function situations(): array
    {
        $list = [];
        foreach ($this->data()['situations'] as $key => $data) {
            if (null !== $situation = Situation::fromArray($key, $data)) {
                $list[$situation->key] = $situation;
            }
        }

        return $list;
    }

    /**
     * @return non-empty-list<string> company sizes ("une PME de 40 salariés"…)
     */
    public function sizes(): array
    {
        return $this->data()['sizes'] ?: ['une PME de 40 salariés'];
    }

    /**
     * @return non-empty-list<string>
     */
    public function cities(): array
    {
        return $this->data()['cities'] ?: ['Paris'];
    }

    /**
     * @return array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>}
     */
    private function data(): array
    {
        /** @var array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>} */
        return $this->data ??= Yaml::parseFile($this->file);
    }
}
