<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Call\Speech\SpeechFigures;
use App\Enum\BriefSection;
use App\Enum\ObjectiveStatus;

/**
 * The coach's debrief of a call: the scorecard graded, the objectives judged, what was found or missed of the hidden
 * brief, the best and worst lines (quoted as said), three tips, and the call in figures.
 */
final readonly class Debrief
{
    /**
     * @param array<string, array{label: string, score: int, comment: string}>                     $criteria   by scorecard key, graded 0 to 10
     * @param list<array{objective: string, status: ObjectiveStatus, evidence: string}>            $objectives
     * @param list<array{section: BriefSection, what: string, how: string}>                        $missed     what was not found, and the question that would have
     * @param list<array{quote: string, why: string}>                                              $best
     * @param list<array{quote: string, why: string, better: string}>                              $worst
     * @param list<string>                                                                         $tips
     */
    public function __construct(
        public array $criteria,
        public string $summary,
        /** Whether a concrete next step was obtained. */
        public bool $nextStep,
        /** Where the deal stands when the call ends. */
        public string $outcome,
        /** The result of the call in a few words, like a headline. */
        public string $result,
        public array $objectives,
        /** How far the call's goal was reached overall; null when it had no objectives. */
        public ?ObjectiveStatus $goal,
        public array $missed,
        public array $best,
        public array $worst,
        public array $tips,
        public ?SpeechFigures $speech = null,
        /** The coach's sentence on the call's figures. */
        public string $speechComment = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'criteria' => $this->criteria,
            'summary' => $this->summary,
            'next_step' => $this->nextStep,
            'outcome' => $this->outcome,
            'result' => $this->result,
            'objectives' => array_map(static fn (array $o): array => ['objective' => $o['objective'], 'status' => $o['status']->value, 'evidence' => $o['evidence']], $this->objectives),
            'goal' => $this->goal?->value,
            'missed' => array_map(static fn (array $m): array => ['section' => $m['section']->value, 'what' => $m['what'], 'how' => $m['how']], $this->missed),
            'best' => $this->best,
            'worst' => $this->worst,
            'tips' => $this->tips,
            'speech' => $this->speech?->toArray(),
            'speech_comment' => $this->speechComment,
        ];
    }

    /**
     * @param array<mixed> $data as stored by {@see toArray()}
     */
    public static function fromArray(array $data): self
    {
        $list = static fn (string $k): array => \is_array($data[$k] ?? null) ? array_values($data[$k]) : [];

        /** @var array<string, array{label: string, score: int, comment: string}> $criteria */
        $criteria = \is_array($data['criteria'] ?? null) ? $data['criteria'] : [];
        /** @var list<array{quote: string, why: string}> $best */
        $best = $list('best');
        /** @var list<array{quote: string, why: string, better: string}> $worst */
        $worst = $list('worst');
        /** @var list<string> $tips */
        $tips = $list('tips');

        return new self(
            criteria: $criteria,
            summary: (string) ($data['summary'] ?? ''),
            nextStep: (bool) ($data['next_step'] ?? false),
            outcome: (string) ($data['outcome'] ?? ''),
            result: (string) ($data['result'] ?? ''),
            objectives: array_map(static fn (array $o): array => ['objective' => (string) $o['objective'], 'status' => ObjectiveStatus::fromModel($o['status'] ?? ''), 'evidence' => (string) ($o['evidence'] ?? '')], $list('objectives')),
            goal: ObjectiveStatus::tryFrom((string) ($data['goal'] ?? '')),
            missed: array_values(array_filter(array_map(static fn (array $m): ?array => null !== ($s = BriefSection::tryFrom((string) ($m['section'] ?? ''))) ? ['section' => $s, 'what' => (string) ($m['what'] ?? ''), 'how' => (string) ($m['how'] ?? '')] : null, $list('missed')))),
            best: $best,
            worst: $worst,
            tips: $tips,
            speech: \is_array($data['speech'] ?? null) ? SpeechFigures::fromArray($data['speech']) : null,
            speechComment: (string) ($data['speech_comment'] ?? ''),
        );
    }
}
