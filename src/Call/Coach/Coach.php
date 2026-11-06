<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Ai\LanguageModel;
use App\Call\Speech\SpeechAnalyzer;
use App\Entity\Call;
use App\Repository\CompanyRepository;

/**
 * The coach: reads the whole call against the company's offer and scorecard, and writes the debrief.
 */
final readonly class Coach
{
    public function __construct(
        private LanguageModel $model,
        private CoachPrompt $prompt,
        private DebriefBuilder $builder,
        private SpeechAnalyzer $speech,
        private CompanyRepository $companies,
    ) {
    }

    /**
     * Debriefs a hung-up call. The brief sections the coach found discovered are marked on the call.
     *
     * @return array{debrief: Debrief, score: int}
     */
    public function debrief(Call $call, \DateTimeImmutable $now): array
    {
        $company = $this->companies->get();
        $scorecard = $company->scorecard();
        $figures = $this->speech->analyze($call);
        if (0 === $call->sellerTurns()) {
            return ['debrief' => $this->builder->empty($call, $scorecard, $figures), 'score' => 0];
        }
        $data = $this->model->json($this->prompt->system($call, $company), $this->prompt->user($call, $figures, $now), 0.4);
        $result = $this->builder->build($call, $data, $scorecard, $figures);
        foreach ($result['discovered'] as $section) {
            $call->reveal($section);
        }

        return ['debrief' => $result['debrief'], 'score' => $result['score']];
    }
}
