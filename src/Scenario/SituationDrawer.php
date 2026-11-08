<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Entity\Company;
use App\Enum\Difficulty;
use App\Enum\Tone;
use Random\Randomizer;

/**
 * Draws a surprise call: a situation of the company's catalogue (not one of the seller's last ones), dressed up at
 * random, around the difficulty the seller's recent scores call for.
 */
final readonly class SituationDrawer
{
    public function __construct(
        private SituationCanvas $canvas,
        private Randomizer $random = new Randomizer(),
    ) {
    }

    /**
     * @param string|null  $key   a given situation (a mission asks for one); null for a surprise
     * @param list<string> $avoid situations played just before, not drawn again right away
     *
     * @throws \LogicException when the company has no situation yet
     */
    public function draw(Company $company, Difficulty $target = Difficulty::Easy, ?string $key = null, array $avoid = []): Draw
    {
        $catalogue = $company->situations();
        if ([] === $catalogue) {
            throw new \LogicException('The company has no call situation yet: finish the onboarding first.');
        }
        $situation = null !== $key && isset($catalogue[$key])
            ? $catalogue[$key]
            : $this->pick(array_values(array_diff_key($catalogue, array_flip($avoid)) ?: $catalogue));
        $sectors = $company->sectors ?: ['business services'];

        return new Draw(
            situation: $situation,
            sector: $this->pick($sectors),
            size: $this->pick($this->canvas->sizes($company->language)),
            city: $this->pick($this->canvas->cities($company->language)),
            tone: $this->pick(Tone::forSituation($situation->key)),
            difficulty: $this->pick($target->around()),
        );
    }

    /**
     * @template T
     *
     * @param non-empty-list<T> $list
     *
     * @return T
     */
    private function pick(array $list): mixed
    {
        return $list[$this->random->getInt(0, \count($list) - 1)];
    }
}
