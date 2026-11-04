<?php

namespace App\Training;

use App\Entity\Mission;
use App\Entity\Scenario;
use App\Entity\Seller;
use App\Entity\Training;
use App\Entity\Workspace;
use App\Game\Level;
use Doctrine\ORM\EntityManagerInterface;

/**
 * L'historique et la progression d'un commercial, et le tableau de l'équipe.
 */
final readonly class Progress
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<Training>
     */
    public function history(Seller $seller, int $limit = 100): array
    {
        return $this->em->getRepository(Training::class)->findBy(['seller' => $seller], ['startedAt' => 'DESC'], $limit);
    }

    /**
     * @return array{sessions: int, done: int, abandoned: int, average: ?int, best: ?int, last: ?int, trend: ?int, criteria: array<string, array{label: string, average: ?float}>, weakest: ?string, strongest: ?string, scores: list<array{id: ?int, score: int, date: string, title: string}>, xp: int, level: array{number: int, name: string, xp: int, from: int, to: ?int, pct: int, next: ?string}, streak: int, goals: int, bySituation: list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>, discovery: ?int}
     */
    public function of(Seller $seller): array
    {
        return self::summarize($this->history($seller, 500));
    }

    /**
     * @param list<Training> $trainings du plus récent au plus ancien
     *
     * @return array{sessions: int, done: int, abandoned: int, average: ?int, best: ?int, last: ?int, trend: ?int, criteria: array<string, array{label: string, average: ?float}>, weakest: ?string, strongest: ?string, scores: list<array{id: ?int, score: int, date: string, title: string}>, xp: int, level: array{number: int, name: string, xp: int, from: int, to: ?int, pct: int, next: ?string}, streak: int, goals: int, bySituation: list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>, discovery: ?int}
     */
    public static function summarize(array $trainings): array
    {
        // Les reprises (un moment rejoué) ne comptent ni dans les moyennes ni dans l'XP.
        $done = array_values(array_filter($trainings, static fn (Training $t): bool => Training::DONE === $t->status && null !== $t->score && null === $t->replayOf));
        $scores = array_map(static fn (Training $t): int => (int) $t->score, $done);
        // Les critères viennent des débriefs eux-mêmes : la grille d'une société peut changer, l'historique reste lisible.
        $labels = [];
        foreach ($done as $t) {
            foreach (\is_array($t->debrief['criteria'] ?? null) ? $t->debrief['criteria'] : [] as $key => $c) {
                $labels[$key] ??= \is_array($c) && \is_string($c['label'] ?? null) ? $c['label'] : (string) $key;
            }
        }
        $criteria = [];
        foreach ([] !== $labels ? $labels : array_map(static fn (array $c): string => $c['label'], Coach::DEFAULT_CRITERIA) as $key => $label) {
            $values = array_values(array_filter(array_map(static fn (Training $t): ?int => $t->debrief['criteria'][$key]['score'] ?? null, $done), static fn (?int $v): bool => null !== $v));
            $criteria[$key] = ['label' => $label, 'average' => [] === $values ? null : round(array_sum($values) / \count($values), 1)];
        }
        $rated = array_filter($criteria, static fn (array $c): bool => null !== $c['average']);
        uasort($rated, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);
        // La tendance : la moyenne des trois dernières sessions face aux trois d'avant.
        $trend = \count($scores) >= 4 ? (int) round(array_sum(\array_slice($scores, 0, 3)) / 3 - array_sum(\array_slice($scores, 3, 3)) / \count(\array_slice($scores, 3, 3))) : null;
        $briefs = array_sum(array_map(static fn (Training $t): int => \count(array_filter($t->scenario->brief, static fn (string $v): bool => '' !== trim($v))), $done));

        return [
            'sessions' => \count($trainings),
            'done' => \count($done),
            'abandoned' => \count(array_filter($trainings, static fn (Training $t): bool => Training::ABANDONED === $t->status)),
            'average' => [] === $scores ? null : (int) round(array_sum($scores) / \count($scores)),
            'best' => [] === $scores ? null : max($scores),
            'last' => $scores[0] ?? null,
            'trend' => $trend,
            'criteria' => $criteria,
            'weakest' => array_key_first($rated),
            'strongest' => array_key_last($rated),
            'scores' => array_reverse(array_map(static fn (Training $t): array => ['id' => $t->id, 'score' => (int) $t->score, 'date' => $t->startedAt->format('d/m'), 'title' => $t->scenario->title], \array_slice($done, 0, 20))),
            'xp' => $xp = array_sum(array_map(Level::xp(...), $done)),
            'level' => Level::of($xp),
            'streak' => Level::streak($trainings, new \DateTimeImmutable()),
            'goals' => \count(array_filter($done, static fn (Training $t): bool => 'atteint' === ($t->debrief['goal_reached'] ?? null))),
            'bySituation' => self::bySituation($done),
            'discovery' => 0 === $briefs ? null : (int) round(array_sum(array_map(static fn (Training $t): int => \count($t->revealed), $done)) / $briefs * 100),
        ];
    }

    /**
     * Les missions d'un commercial et où il en est (les plus urgentes d'abord ; une mission finie reste visible).
     *
     * @return list<array{mission: Mission, done: int, complete: bool, overdue: bool}>
     */
    public function missions(Seller $seller, ?\DateTimeImmutable $now = null): array
    {
        if (null === $seller->workspace) {
            return [];
        }
        $now ??= new \DateTimeImmutable();
        $trainings = $this->history($seller, 500);
        $out = [];
        foreach ($this->em->getRepository(Mission::class)->findBy(['workspace' => $seller->workspace], ['createdAt' => 'DESC']) as $mission) {
            if ($mission->concerns($seller)) {
                $done = $mission->progress($trainings);
                $out[] = ['mission' => $mission, 'done' => $done, 'complete' => $done >= $mission->calls, 'overdue' => $mission->overdue($now)];
            }
        }
        usort($out, static fn (array $a, array $b): int => [$a['complete'], $a['mission']->dueAt ?? new \DateTimeImmutable('2100-01-01')] <=> [$b['complete'], $b['mission']->dueAt ?? new \DateTimeImmutable('2100-01-01')]);

        return $out;
    }

    /**
     * Les missions de l'équipe, avec la progression de chaque commercial concerné.
     *
     * @return list<array{mission: Mission, members: list<array{seller: Seller, done: int}>}>
     */
    public function teamMissions(Workspace $space): array
    {
        $sellers = $this->em->getRepository(Seller::class)->findBy(['workspace' => $space], ['name' => 'ASC']);
        $history = [];
        $out = [];
        foreach ($this->em->getRepository(Mission::class)->findBy(['workspace' => $space], ['createdAt' => 'DESC']) as $mission) {
            $members = [];
            foreach ($sellers as $seller) {
                if ($mission->concerns($seller) && (null !== $mission->assignee || !$seller->isManager())) {
                    $history[(int) $seller->id] ??= $this->history($seller, 500);
                    $members[] = ['seller' => $seller, 'done' => $mission->progress($history[(int) $seller->id])];
                }
            }
            $out[] = ['mission' => $mission, 'members' => $members];
        }

        return $out;
    }

    /**
     * Ce que le commercial vient de jouer : les situations des trois derniers appels (pas retirées tout de suite) et les
     * noms des personnes et entreprises des derniers (le générateur en invente d'autres).
     *
     * @return array{keys: list<string>, names: list<string>}
     */
    public function recent(Seller $seller): array
    {
        $scenarios = array_values(array_filter(
            $this->em->getRepository(Scenario::class)->findBy(['author' => $seller], ['createdAt' => 'DESC'], 12),
            static fn (Scenario $s): bool => null !== $s->situation,
        ));
        $names = [];
        foreach ($scenarios as $s) {
            $names[] = $s->persona['name'];
            $names[] = trim(explode(',', $s->persona['company'])[0]);
        }

        return [
            'keys' => array_values(array_unique(array_map(static fn (Scenario $s): string => (string) $s->situation, \array_slice($scenarios, 0, 3)))),
            'names' => array_values(array_unique(array_filter($names))),
        ];
    }

    /**
     * Les chiffres par type de situation : combien d'appels, la note moyenne, la part d'objectifs atteints.
     *
     * @param list<Training> $done
     *
     * @return list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>
     */
    public static function bySituation(array $done): array
    {
        $groups = [];
        foreach ($done as $t) {
            if (null !== $t->scenario->situation) {
                $groups[$t->scenario->situation][] = $t;
            }
        }
        $out = [];
        foreach ($groups as $key => $list) {
            $sit = $list[0]->scenario->situationInfo();
            $out[] = [
                'key' => $key,
                'label' => $sit['label'] ?? $key,
                'emoji' => $sit['emoji'] ?? '📞',
                'calls' => \count($list),
                'average' => (int) round(array_sum(array_map(static fn (Training $t): int => (int) $t->score, $list)) / \count($list)),
                'goals' => (int) round(\count(array_filter($list, static fn (Training $t): bool => 'atteint' === ($t->debrief['goal_reached'] ?? null))) / \count($list) * 100),
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['calls'] <=> $a['calls']);

        return $out;
    }

    /**
     * Le tableau d'équipe : chaque commercial et ses chiffres, puis les scénarios et leur note moyenne.
     *
     * @return array{sellers: list<array{seller: Seller, stats: array<string, mixed>, lastAt: ?\DateTimeImmutable}>, scenarios: list<array{scenario: Scenario, sessions: int, average: ?int}>, situations: list<array{key: string, label: string, emoji: string, calls: int, average: int, goals: int}>, recent: list<Training>}
     */
    public function team(Workspace $space): array
    {
        $members = $this->em->getRepository(Seller::class)->findBy(['workspace' => $space], ['name' => 'ASC']);
        $all = [] === $members ? [] : $this->em->getRepository(Training::class)->findBy(['seller' => $members], ['startedAt' => 'DESC']);
        $bySeller = [];
        $byScenario = [];
        foreach ($all as $t) {
            $bySeller[(int) $t->seller->id][] = $t;
            $byScenario[(int) $t->scenario->id][] = $t;
        }
        $sellers = [];
        foreach ($members as $seller) {
            $list = $bySeller[(int) $seller->id] ?? [];
            $sellers[] = ['seller' => $seller, 'stats' => self::summarize($list), 'lastAt' => ($list[0] ?? null)?->startedAt];
        }
        $scenarios = [];
        foreach ($this->em->getRepository(Scenario::class)->findBy(['situation' => null, 'workspace' => $space], ['title' => 'ASC']) as $scenario) {
            $done = array_filter($byScenario[(int) $scenario->id] ?? [], static fn (Training $t): bool => null !== $t->score);
            $scenarios[] = ['scenario' => $scenario, 'sessions' => \count($byScenario[(int) $scenario->id] ?? []), 'average' => [] === $done ? null : (int) round(array_sum(array_map(static fn (Training $t): int => (int) $t->score, $done)) / \count($done))];
        }

        $done = array_values(array_filter($all, static fn (Training $t): bool => Training::DONE === $t->status && null !== $t->score));

        return ['sellers' => $sellers, 'scenarios' => $scenarios, 'situations' => self::bySituation($done), 'recent' => \array_slice($all, 0, 15)];
    }
}
