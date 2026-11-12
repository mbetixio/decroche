<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Agent\AgentContext;
use App\Ai\LanguageModel;
use App\Ai\Output\CoachReport;
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
        private AgentContext $context,
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
        $figures = $this->speech->analyze($call, $company->language);
        if (0 === $call->sellerTurns()) {
            return ['debrief' => $this->builder->empty($call, $scorecard, $figures, $company->language), 'score' => 0];
        }
        // The coach's tools (earlier debriefs, the exact lines, what the client did) work on this call.
        $this->context->forCall($call);
        $data = $this->model->work('coach', $this->prompt->system($call, $company), $this->prompt->user($call, $figures, $now), CoachReport::class, 0.4)->toArray();
        $result = $this->builder->build($call, $data, $scorecard, $figures);
        foreach ($result['discovered'] as $section) {
            $call->reveal($section);
        }

        return ['debrief' => $result['debrief'], 'score' => $result['score']];
    }
}
