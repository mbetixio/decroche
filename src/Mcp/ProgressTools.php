<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Mission;
use App\Progress\MissionTracker;
use App\Progress\ProgressReport;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * The seller's own figures: the difficulty of their next calls, scores and trend, criteria, missions, last calls.
 */
final readonly class ProgressTools
{
    public function __construct(
        private CurrentMember $me,
        private ProgressReport $progress,
        private MissionTracker $missions,
        private CallRepository $calls,
        private CompanyRepository $companies,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'my_progress', title: 'My progress', description: 'The seller\'s figures: the difficulty of their next surprise calls (it follows their last five scores), calls, average score and trend, average by criterion with the strongest and weakest, results by situation, the missions their manager gave them (to play one: new_call with its situation key), and the last calls.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function myProgress(): array
    {
        $member = $this->me->member();

        return ['member' => $member->name, 'company' => $this->companies->get()->name] + $this->report($member);
    }

    /**
     * A member's figures, as given to them and to their managers.
     *
     * @return array<string, mixed>
     */
    public function report(Member $member): array
    {
        $history = $this->calls->history($member);

        return $this->progress->of($member, $history)->toArray() + [
            'missions' => array_map(fn (array $m): array => self::mission($m) + ['situation' => $this->companies->get()->situation($m['mission']->situation)?->label], $this->missions->of($member, $history)),
            'recent' => array_map(static fn (Call $c): array => [
                'call' => $c->id, 'scenario' => $c->scenario->title, 'date' => $c->startedAt->format('Y-m-d H:i'), 'status' => $c->status->value, 'score' => $c->score,
            ], \array_slice($history, 0, 10)),
        ];
    }

    /**
     * @param array{mission: Mission, done: int, complete: bool, overdue: bool} $row
     *
     * @return array<string, mixed>
     */
    private static function mission(array $row): array
    {
        $mission = $row['mission'];

        return [
            'calls' => $mission->calls, 'done' => $row['done'], 'target_score' => $mission->target, 'situation_key' => $mission->situation,
            'due' => $mission->dueAt?->format('Y-m-d'), 'note' => $mission->note, 'complete' => $row['complete'], 'overdue' => $row['overdue'],
        ];
    }
}
