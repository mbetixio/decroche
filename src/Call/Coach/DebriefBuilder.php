<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Ai\Data;
use App\Call\Speech\SpeechFigures;
use App\Entity\Call;
use App\Enum\BriefSection;
use App\Enum\Language;
use App\Enum\ObjectiveStatus;
use App\Model\Criterion;
use App\Model\Line;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns what the model returned into a reliable debrief and a score: every criterion of the scorecard graded 0 to 10,
 * every objective judged, only quotes the seller really said, and the score computed here rather than by the model
 * (70 % skills, 30 % objectives).
 */
final readonly class DebriefBuilder
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<mixed>             $data      the model's JSON
     * @param array<string, Criterion> $scorecard
     *
     * @return array{debrief: Debrief, score: int, discovered: list<BriefSection>}
     */
    public function build(Call $call, array $data, array $scorecard, SpeechFigures $figures): array
    {
        $criteria = [];
        foreach ($scorecard as $key => $criterion) {
            $c = Data::map(Data::map($data['criteria'] ?? null)[$key] ?? null);
            $criteria[$key] = ['label' => $criterion->label, 'score' => max(0, min(10, Data::int($c['score'] ?? 0))), 'comment' => self::text($c['comment'] ?? '')];
        }
        $skills = [] === $criteria ? 0.0 : array_sum(array_column($criteria, 'score')) / \count($criteria) * 10;

        // The call's objectives, judged one by one: they count for 30 % of the score.
        $judged = \is_array($data['objectives'] ?? null) ? array_values($data['objectives']) : [];
        $objectives = [];
        foreach ($call->scenario->objectives() as $i => $objective) {
            $j = \is_array($judged[$i] ?? null) ? $judged[$i] : [];
            $objectives[] = ['objective' => $objective, 'status' => ObjectiveStatus::fromModel($j['status'] ?? ''), 'evidence' => self::text($j['evidence'] ?? '')];
        }
        $reached = [] === $objectives ? null : array_sum(array_map(static fn (array $o): float => $o['status']->weight(), $objectives)) / \count($objectives);
        $score = (int) round(null === $reached ? $skills : 0.7 * $skills + 30 * $reached);

        // The persona marks what it gives away, but sometimes forgets: the coach, who reads everything, completes.
        $discovered = [];
        foreach (\is_array($data['discovered'] ?? null) ? $data['discovered'] : [] as $key) {
            if (null !== ($section = BriefSection::tryFrom(\is_string($key) ? trim($key) : '')) && '' !== $call->scenario->brief($section)) {
                $discovered[] = $section;
            }
        }
        $missed = [];
        foreach (\is_array($data['missed'] ?? null) ? $data['missed'] : [] as $m) {
            $section = \is_array($m) ? BriefSection::tryFrom(self::text($m['rubric'] ?? '')) : null;
            if (null !== $section && !\in_array($section, $discovered, true) && !$call->isRevealed($section)) {
                $missed[$section->value] = ['section' => $section, 'what' => self::text($m['what'] ?? ''), 'how' => self::text($m['how'] ?? '')];
            }
        }

        $said = array_values(array_map(static fn (Line $l): string => $l->text, array_filter($call->lines(), static fn (Line $l): bool => $l->isSeller())));

        return ['score' => $score, 'discovered' => $discovered, 'debrief' => new Debrief(
            criteria: $criteria,
            summary: self::text($data['summary'] ?? ''),
            nextStep: (bool) ($data['next_step'] ?? false),
            outcome: self::text($data['outcome'] ?? ''),
            result: self::text($data['result'] ?? ''),
            objectives: $objectives,
            goal: null === $reached ? null : ($reached >= 0.99 ? ObjectiveStatus::Reached : ($reached > 0 ? ObjectiveStatus::Partial : ObjectiveStatus::Missed)),
            missed: array_values($missed),
            best: array_map(static fn (array $q): array => ['quote' => $q['quote'], 'why' => $q['why']], self::quotes($data['best'] ?? null, $said)),
            worst: self::quotes($data['worst'] ?? null, $said),
            tips: \array_slice(array_values(array_filter(array_map(self::text(...), \is_array($data['tips'] ?? null) ? $data['tips'] : []))), 0, 3),
            speech: $figures,
            speechComment: self::text($data['speech'] ?? ''),
        )];
    }

    /**
     * The debrief of a call hung up before the seller said a word: nothing to grade.
     *
     * @param array<string, Criterion> $scorecard
     */
    public function empty(Call $call, array $scorecard, SpeechFigures $figures, Language $language): Debrief
    {
        $objectives = $call->scenario->objectives();
        $t = fn (string $key): string => $this->translator->trans($key, locale: $language->value);

        return new Debrief(
            criteria: array_map(fn (Criterion $c): array => ['label' => $c->label, 'score' => 0, 'comment' => $t('debrief.empty.comment')], $scorecard),
            summary: $t('debrief.empty.summary'),
            nextStep: false,
            outcome: $t('debrief.empty.outcome'),
            result: $t('debrief.empty.result'),
            objectives: array_map(static fn (string $o): array => ['objective' => $o, 'status' => ObjectiveStatus::Missed, 'evidence' => ''], $objectives),
            goal: [] === $objectives ? null : ObjectiveStatus::Missed,
            missed: [],
            best: [],
            worst: [],
            tips: [$t('debrief.empty.tip')],
            speech: $figures,
        );
    }

    /**
     * Quotes the seller really said (give or take punctuation and case), two at most.
     *
     * @param list<string> $said
     *
     * @return list<array{quote: string, why: string, better: string}>
     */
    private static function quotes(mixed $list, array $said): array
    {
        $kept = [];
        foreach (Data::rows($list) as $q) {
            if (self::wasSaid(self::text($q['quote'] ?? ''), $said)) {
                $kept[] = ['quote' => self::text($q['quote'] ?? ''), 'why' => self::text($q['why'] ?? ''), 'better' => self::text($q['better'] ?? '')];
            }
        }

        return \array_slice($kept, 0, 2);
    }

    /**
     * @param list<string> $said
     */
    private static function wasSaid(string $quote, array $said): bool
    {
        $norm = static fn (string $s): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)));
        $q = $norm($quote);
        if (mb_strlen($q) < 4) {
            return false;
        }
        foreach ($said as $line) {
            $l = $norm($line);
            similar_text($q, $l, $percent);
            if (str_contains($l, $q) || (str_contains($q, $l) && mb_strlen($l) > 10) || $percent >= 80) {
                return true;
            }
        }

        return false;
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
