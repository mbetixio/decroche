<?php

declare(strict_types=1);

namespace App\Ai\Output;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

/**
 * The coach's reading of a call (App\Call\Coach\Coach), checked and scored by App\Call\Coach\DebriefBuilder.
 * The criteria come as a list, since each company sets its own scorecard: a JSON schema cannot name their keys.
 */
final class CoachReport extends Output
{
    /**
     * @param list<CriterionGrade>   $criteria
     * @param list<ObjectiveVerdict> $objectives
     * @param list<string>           $discovered
     * @param list<MissedSection>    $missed
     * @param list<QuotedLine>       $best
     * @param list<QuotedLine>       $worst
     * @param list<string>           $tips
     */
    public function __construct(
        #[Schema(description: 'One grade per criterion of the scorecard')]
        public array $criteria,
        #[Schema(description: 'Two or three sentences: what happened and the seller\'s level')]
        public string $summary,
        #[Schema(description: 'Was a concrete next step obtained?')]
        public bool $nextStep,
        #[Schema(description: 'One sentence: where the deal stands at the end of the meeting')]
        public string $outcome,
        #[Schema(description: 'The result of the call in a few words, like a headline')]
        public string $result,
        #[Schema(description: 'One verdict per objective of the call, in order')]
        public array $objectives,
        #[Schema(description: 'The keys of the brief sections the seller really got out in the conversation')]
        public array $discovered,
        public array $missed,
        #[Schema(maxItems: 2)]
        public array $best,
        #[Schema(maxItems: 2)]
        public array $worst,
        #[Schema(description: 'One sentence on the form of the call, only if the figures say something; otherwise empty')]
        public string $speech,
        #[Schema(description: 'Three concrete, actionable tips for the next meeting', maxItems: 3)]
        public array $tips,
    ) {
    }

    /**
     * In the shape DebriefBuilder reads: the criteria keyed by their key.
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['criteria'] = array_column(array_map(static fn (CriterionGrade $g): array => $g->toArray(), $this->criteria), null, 'key');
        $data['next_step'] = $data['nextStep'];
        unset($data['nextStep']);

        return $data;
    }
}
