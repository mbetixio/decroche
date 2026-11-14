<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Ai\ModelException;
use App\Call\CallException;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Progress\ProgressReport;
use App\Repository\CompanyRepository;
use App\Repository\ScenarioRepository;
use App\Security\Usage;
use App\Security\UsageLimits;

/**
 * The game: a surprise call drawn and written for a member, at the difficulty their recent scores call for, avoiding what they just played.
 */
final readonly class SurpriseCalls
{
    public function __construct(
        private SituationDrawer $drawer,
        private ScenarioWriter $writer,
        private CompanyRepository $companies,
        private ScenarioRepository $scenarios,
        private ProgressReport $progress,
        private UsageLimits $limits,
    ) {
    }

    /**
     * Draws and writes a surprise call (some fifteen seconds), retrying once with another draw if the model's answer
     * is unusable.
     *
     * @param string|null   $situation a given situation (a mission asks for one); null for a surprise
     * @param string|null   $focus     a criterion of the scorecard to train on (from a debrief); unknown keys are ignored
     * @param \Closure|null $step      told each step of the drawing and writing
     *
     * @throws ModelException when the model fails twice
     * @throws CallException  when the member's writing quota is spent for now
     */
    public function draw(Member $member, ?string $situation = null, ?string $focus = null, ?\Closure $step = null): Scenario
    {
        $this->limits->consume($member, Usage::Writing);
        $company = $this->companies->get();
        $criterion = null !== $focus ? ($company->scorecard()[$focus] ?? null) : null;
        $target = $this->progress->of($member)->challenge->difficulty;
        $recent = $this->scenarios->recentSurpriseCalls($member);
        $keys = array_values(array_unique(array_filter(array_map(static fn (Scenario $s): ?string => $s->situation()?->key, \array_slice($recent, 0, 3)))));
        $names = [];
        foreach ($recent as $scenario) {
            $names[] = $scenario->persona()->name;
            $names[] = trim(explode(',', $scenario->persona()->company)[0]);
        }
        $names = array_values(array_unique(array_filter($names)));

        $write = function () use ($company, $target, $situation, $keys, $member, $names, $criterion, $step): Scenario {
            $draw = $this->drawer->draw($company, $target, $situation, $keys);
            if (null !== $step) {
                $step('draw.situation', ['%situation%' => $draw->situation->label]);
            }

            return $this->writer->surprise($draw, $member, $names, $criterion, $step);
        };
        try {
            $scenario = $write();
        } catch (ModelException) {
            $scenario = $write();
        }
        $scenario->focus = $criterion?->key;
        $this->scenarios->save($scenario);

        return $scenario;
    }
}
