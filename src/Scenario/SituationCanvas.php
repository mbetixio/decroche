<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Enum\Language;
use App\Model\Situation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The common canvas (config/sparring/{language}/situations.yaml): the call situations nearly every B2B sales team
 * meets, which the analysis of a company adapts to its business, and the random dressing of a surprise call.
 */
final class SituationCanvas
{
    /** @var array<string, array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>}> by language */
    private array $data = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/sparring')]
        private readonly string $directory,
    ) {
    }

    /**
     * @return array<string, Situation>
     */
    public function situations(Language $language): array
    {
        $list = [];
        foreach ($this->data($language)['situations'] as $key => $data) {
            if (null !== $situation = Situation::fromArray($key, $data)) {
                $list[$situation->key] = $situation;
            }
        }

        return $list;
    }

    /**
     * @return non-empty-list<string> company sizes ("a 40-person SME"…)
     */
    public function sizes(Language $language): array
    {
        return $this->data($language)['sizes'] ?: ['a 40-person SME'];
    }

    /**
     * @return non-empty-list<string>
     */
    public function cities(Language $language): array
    {
        return $this->data($language)['cities'] ?: ['Paris'];
    }

    /**
     * @return array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>}
     */
    private function data(Language $language): array
    {
        /* @var array{sizes: list<string>, cities: list<string>, situations: array<string, array<mixed>>} */
        return $this->data[$language->value] ??= Yaml::parseFile(\sprintf('%s/%s/situations.yaml', $this->directory, $language->value));
    }
}
