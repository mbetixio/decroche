<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Ai\Data;
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
        $criteria = [];
        foreach (Data::map($data['criteria'] ?? null) as $key => $c) {
            $c = Data::map($c);
            $criteria[(string) $key] = ['label' => Data::string($c['label'] ?? ''), 'score' => Data::int($c['score'] ?? 0), 'comment' => Data::string($c['comment'] ?? '')];
        }
        $missed = [];
        foreach (Data::rows($data['missed'] ?? null) as $m) {
            if (null !== $section = BriefSection::tryFrom(Data::string($m['section'] ?? ''))) {
                $missed[] = ['section' => $section, 'what' => Data::string($m['what'] ?? ''), 'how' => Data::string($m['how'] ?? '')];
            }
        }

        return new self(
            criteria: $criteria,
            summary: Data::string($data['summary'] ?? ''),
            nextStep: true === ($data['next_step'] ?? false),
            outcome: Data::string($data['outcome'] ?? ''),
            result: Data::string($data['result'] ?? ''),
            objectives: array_map(static fn (array $o): array => ['objective' => Data::string($o['objective'] ?? ''), 'status' => ObjectiveStatus::fromModel($o['status'] ?? ''), 'evidence' => Data::string($o['evidence'] ?? '')], Data::rows($data['objectives'] ?? null)),
            goal: ObjectiveStatus::tryFrom(Data::string($data['goal'] ?? '')),
            missed: $missed,
            best: array_map(static fn (array $q): array => ['quote' => Data::string($q['quote'] ?? ''), 'why' => Data::string($q['why'] ?? '')], Data::rows($data['best'] ?? null)),
            worst: array_map(static fn (array $q): array => ['quote' => Data::string($q['quote'] ?? ''), 'why' => Data::string($q['why'] ?? ''), 'better' => Data::string($q['better'] ?? '')], Data::rows($data['worst'] ?? null)),
            tips: Data::strings($data['tips'] ?? null),
            speech: \is_array($data['speech'] ?? null) ? SpeechFigures::fromArray($data['speech']) : null,
            speechComment: Data::string($data['speech_comment'] ?? ''),
        );
    }
}
