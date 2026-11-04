<?php

namespace App\Training;

use App\Ai\Brain;
use App\Entity\Scenario;
use App\Entity\Training;

/**
 * Le débrief : une note par critère, le brief caché dévoilé, ce qui a été manqué, les meilleures et pires répliques
 * citées, trois conseils.
 */
final readonly class Coach
{
    /** La grille de Sparring, que chaque société peut remplacer par la sienne (son espace). */
    public const DEFAULT_CRITERIA = [
        'decouverte' => ['label' => 'Découverte du besoin', 'hint' => 'fait parler le client de sa situation, de ses enjeux, de son contexte avant de proposer'],
        'questions' => ['label' => 'Questions ouvertes', 'hint' => 'des questions ouvertes, précises, une à la fois, qui font avancer'],
        'ecoute' => ['label' => 'Écoute et reformulation', 'hint' => 'rebondit sur ce que dit le client, reformule, ne coupe pas, laisse parler'],
        'objections' => ['label' => 'Traitement des objections', 'hint' => 'accueille l\'objection, la creuse, y répond avec les vrais arguments de l\'offre'],
        'valeur' => ['label' => 'Proposition de valeur', 'hint' => 'relie l\'offre au besoin exprimé, sans catalogue ni promesse hors offre'],
        'closing' => ['label' => 'Closing / prochaine étape', 'hint' => 'propose lui-même une prochaine étape concrète, datée, et la verrouille'],
    ];


    public function __construct(
        private Brain $brain,
    ) {
    }

    /**
     * @return array{debrief: array<string, mixed>, score: int}
     */
    public function debrief(Training $training): array
    {
        $sellerLines = array_values(array_map(static fn (array $m): string => $m['content'], array_filter($training->messages, static fn (array $m): bool => Training::SELLER === $m['role'])));
        if ([] === $sellerLines) {
            return ['debrief' => $this->empty($training), 'score' => 0];
        }
        $data = $this->brain->json($this->system($training), $this->prompt($training), 0.4);

        return $this->shape($training, $data, $sellerLines);
    }

    public function system(?Training $training = null): string
    {
        $company = $training?->scenario->workspace->name ?? 'Etixio';
        $profile = null !== $training && $training->seller->hasProfile() ? "\nLe commercial se décrit ainsi (adapte tes conseils à son expérience et à ce qu'il vend) : ".trim($training->seller->profile) : '';
        $grid = self::grid($training);
        $criteria = implode("\n", array_map(static fn (string $k, array $c): string => "- {$k} : {$c['label']}".('' !== $c['hint'] ? " ({$c['hint']})" : ''), array_keys($grid), $grid));
        $first = (string) array_key_first($grid);
        $count = \count($grid);

        return <<<PROMPT
            Tu es un coach commercial expérimenté, exigeant mais bienveillant, qui débriefe un commercial de {$company} après un
            rendez-vous d'entraînement contre un interlocuteur simulé. Tu notes les compétences, pas la chance : un commercial
            qui a posé les bonnes questions mérite une bonne note même si le client n'a pas signé.
            L'offre réelle de {$company}, à laquelle tu confrontes ce que le commercial a dit :
            {$this->offer($training)}
            Pénalise (en proposition de valeur et dans les répliques à retravailler) toute promesse que l'offre ne permet pas
            de tenir (ce que la société ne propose pas, un délai ou un tarif inventé…) et toute réponse faible à une
            objection classique ; valorise l'usage juste des vrais arguments de l'offre.{$profile}
            Les critères, notés de 0 à 10 (5 = correct, 8 = très bon, 10 = exemplaire ; 0 à 2 si la compétence est absente) :
            {$criteria}
            Réponds uniquement avec un objet JSON, en français, de cette forme :
            {"criteria": {"{$first}": {"score": 0, "comment": "une phrase, factuelle, qui s'appuie sur la conversation"}, … les {$count} critères, avec ces clés},
             "summary": "deux ou trois phrases : ce qui s'est passé et le niveau du commercial",
             "next_step": true ou false (une prochaine étape concrète a-t-elle été obtenue ?),
             "outcome": "une phrase : où en est l'affaire à la fin du rendez-vous",
             "result": "le résultat de l'appel en quelques mots, comme un titre (« Rendez-vous calé jeudi à 10 h avec le DSI », « Le client raccroche agacé », « Plan d'action accepté »)",
             "objectives": [{"objective": "l'objectif, tel qu'il est donné", "status": "atteint, partiel ou manqué", "evidence": "ce qui, dans la conversation, le montre"}],
             "discovered": ["les clés des rubriques du brief que le commercial a réellement fait sortir dans la conversation, même partiellement mais sur l'essentiel"],
             "missed": [{"rubric": "une clé du brief non découverte (budget, pain, decision, objections, yes, secret)", "what": "ce qu'il fallait découvrir", "how": "la question qui l'aurait fait sortir"}],
             "best": [{"quote": "une réplique du commercial, citée mot pour mot", "why": "pourquoi elle est bonne"}],
             "worst": [{"quote": "une réplique du commercial, citée mot pour mot", "why": "ce qui ne va pas", "better": "ce qu'il aurait pu dire à la place"}],
             "speech": "une phrase sur la forme de l'appel (temps de parole, questions, tics, débit), seulement si les chiffres disent quelque chose",
             "tips": ["trois conseils concrets et actionnables pour le prochain rendez-vous"]}
            objectives : un élément par objectif de l'appel s'il y en a, dans l'ordre ; sois strict, un objectif n'est
            atteint que si la conversation le montre clairement et grâce au commercial : si c'est le client qui a proposé de
            lui-même la prochaine étape sans que le commercial la demande, l'objectif est au plus partiel, et le closing
            ne peut pas être bien noté.
            best et worst : deux répliques au plus chacune, citées exactement telles que le commercial les a écrites.
            Attribue chaque réplique à la bonne personne : les lignes COMMERCIAL sont du commercial, les autres du client ;
            ne reproche jamais au commercial ce que le client a dit ou fait. N'invente rien : chaque fait, objection ou
            citation que tu évoques doit figurer dans la conversation. Si l'appel a été mené à la voix, les répliques du
            commercial sont des transcriptions automatiques : mots mal reconnus, pas de ponctuation, phrases parfois coupées
            par la détection de fin de parole ; ne le pénalise pas pour ça. Ce que le commercial dit de sa propre société
            (implantations, effectifs, références) peut être plus récent que la fiche d'offre : ne le sanctionne que s'il
            promet ce que l'offre exclut explicitement.
            PROMPT;
    }

    private function offer(?Training $training): string
    {
        return $training?->scenario->workspace->offer ?? \App\Game\Etixio::OFFER;
    }

    public function prompt(Training $training): string
    {
        $s = $training->scenario;
        $brief = '';
        foreach (Scenario::BRIEF as $key => $label) {
            if ('' !== trim($s->brief[$key] ?? '')) {
                $brief .= "- [{$key}] {$label} : ".trim($s->brief[$key]).(\in_array($key, $training->revealed, true) ? ' (le client l\'a lâché)' : '')."\n";
            }
        }
        $transcript = implode("\n", array_map(static fn (array $m): string => (Training::SELLER === $m['role'] ? 'COMMERCIAL' : 'CLIENT').' : '.$m['content'], $training->messages));
        $sp = Speech::analyze($training);
        $figures = \sprintf('L\'appel en chiffres : le commercial a eu %d %% de la parole (%d mots contre %d), %d question(s) dont %d ouverte(s), %d tic(s) de langage%s, une plus longue tirade de %d mots%s%s.',
            $sp['talk_share'], $sp['seller_words'], $sp['persona_words'], $sp['questions'], $sp['open_questions'], $sp['fillers'],
            [] === $sp['filler_words'] ? '' : ' ('.implode(', ', array_keys($sp['filler_words'])).')', $sp['longest'],
            null !== $sp['wpm'] ? ', un débit de '.$sp['wpm'].' mots par minute' : '',
            $sp['voice'] ? ' ; l\'appel a été mené à la voix (les répliques sont des transcriptions : ignore l\'orthographe et la ponctuation)' : '');
        $end = match ($training->endedBy) {
            'clock' => 'L\'horloge a mis fin au rendez-vous (durée maximale atteinte).',
            'persona' => 'Le client a mis fin au rendez-vous.',
            default => 'Le commercial a mis fin au rendez-vous.',
        };
        $p = $s->persona;
        $sit = $s->situationInfo();
        $call = null === $sit ? '' : "C'est un appel téléphonique : {$sit['label']}.\nLa situation vue du client : ".trim($s->brief['setup'] ?? '')."\n".($s->material ? "Le document que le commercial avait sous les yeux :\n{$s->material}\n" : '');
        $objectives = [] === $s->objectiveList() ? '' : "Les objectifs de l'appel :\n".implode("\n", array_map(static fn (int $i, string $o): string => ($i + 1).'. '.$o, array_keys($s->objectiveList()), $s->objectiveList()))."\n";

        return <<<PROMPT
            Le scénario : « {$s->title} » ({$s->categoryLabel()}, difficulté {$s->difficultyLabel()}).
            Le client : {$p['name']}, {$p['role']} chez {$p['company']}. {$p['personality']}
            Ce que le commercial savait : {$s->context}
            Son objectif : {$s->goal}
            {$objectives}{$call}
            Le brief caché du client :
            {$brief}
            La conversation ({$training->durationMinutes()} min sur {$s->minutes}) :
            {$transcript}

            {$figures}
            {$end}
            PROMPT;
    }

    /**
     * Ce que le modèle a rendu, remis d'aplomb : les critères de la grille notés, des citations vraiment dites.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $sellerLines
     *
     * @return array{debrief: array<string, mixed>, score: int}
     */
    public function shape(Training $training, array $data, array $sellerLines): array
    {
        // Le persona note ce qu'il lâche, mais il lui arrive d'oublier : le coach, qui relit tout, complète.
        foreach (\is_array($data['discovered'] ?? null) ? $data['discovered'] : [] as $key) {
            if (\is_string($key) && '' !== trim($training->scenario->brief[$key] ?? '')) {
                $training->reveal($key);
            }
        }
        $criteria = [];
        foreach (self::grid($training) as $key => ['label' => $label]) {
            $c = \is_array($data['criteria'][$key] ?? null) ? $data['criteria'][$key] : [];
            $criteria[$key] = ['label' => $label, 'score' => max(0, min(10, (int) round((float) ($c['score'] ?? 0)))), 'comment' => self::str($c['comment'] ?? '')];
        }
        $skills = array_sum(array_column($criteria, 'score')) / \count($criteria) * 10;
        // Les objectifs de l'appel, jugés un à un ; ils comptent pour 30 % de la note.
        $given = $training->scenario->objectiveList();
        $judged = \is_array($data['objectives'] ?? null) ? array_values($data['objectives']) : [];
        $objectives = [];
        foreach ($given as $i => $objective) {
            $j = \is_array($judged[$i] ?? null) ? $judged[$i] : [];
            $status = mb_strtolower(self::str($j['status'] ?? ''));
            $objectives[] = ['objective' => $objective, 'status' => str_starts_with($status, 'atteint') ? 'atteint' : (str_starts_with($status, 'partiel') ? 'partiel' : 'manqué'), 'evidence' => self::str($j['evidence'] ?? '')];
        }
        $reached = [] === $objectives ? null : array_sum(array_map(static fn (array $o): float => match ($o['status']) { 'atteint' => 1.0, 'partiel' => 0.5, default => 0.0 }, $objectives)) / \count($objectives);
        $score = (int) round(null === $reached ? $skills : 0.7 * $skills + 30 * $reached);
        $quotes = static fn (mixed $list, array $keys): array => \array_slice(array_values(array_filter(array_map(
            static fn (mixed $q): ?array => \is_array($q) && self::said(self::str($q['quote'] ?? ''), $sellerLines) ? array_map(static fn (string $k): string => self::str($q[$k] ?? ''), array_combine($keys, $keys)) : null,
            \is_array($list) ? $list : [],
        ))), 0, 2);
        $missed = [];
        foreach (\is_array($data['missed'] ?? null) ? $data['missed'] : [] as $m) {
            $key = \is_array($m) ? self::str($m['rubric'] ?? '') : '';
            if (isset(Scenario::BRIEF[$key]) && !\in_array($key, $training->revealed, true)) {
                $missed[$key] = ['rubric' => $key, 'label' => Scenario::BRIEF[$key], 'what' => self::str($m['what'] ?? ''), 'how' => self::str($m['how'] ?? '')];
            }
        }

        return ['score' => $score, 'debrief' => [
            'criteria' => $criteria,
            'summary' => self::str($data['summary'] ?? ''),
            'next_step' => (bool) ($data['next_step'] ?? false),
            'outcome' => self::str($data['outcome'] ?? ''),
            'result' => self::str($data['result'] ?? ''),
            'speech_comment' => self::str($data['speech'] ?? ''),
            'objectives' => $objectives,
            'goal_reached' => null === $reached ? null : ($reached >= 0.99 ? 'atteint' : ($reached > 0 ? 'partiel' : 'manqué')),
            'missed' => array_values($missed),
            'best' => $quotes($data['best'] ?? [], ['quote', 'why']),
            'worst' => $quotes($data['worst'] ?? [], ['quote', 'why', 'better']),
            'tips' => \array_slice(array_values(array_filter(array_map(self::str(...), \is_array($data['tips'] ?? null) ? $data['tips'] : []))), 0, 3),
            'brief' => self::brief($training),
        ]];
    }

    /**
     * Le brief caché, dévoilé rubrique par rubrique, avec ce qui a été découvert.
     *
     * @return list<array{key: string, label: string, text: string, found: bool}>
     */
    public static function brief(Training $training): array
    {
        return $training->briefSheet();
    }

    /**
     * La grille de la société du commercial (celle de Sparring par défaut).
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function grid(?Training $training): array
    {
        return $training?->scenario->workspace?->scorecard() ?? self::DEFAULT_CRITERIA;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(Training $training): array
    {
        $criteria = array_map(static fn (array $c): array => ['label' => $c['label'], 'score' => 0, 'comment' => 'Aucune réplique : rien à évaluer.'], self::grid($training));

        return ['criteria' => $criteria, 'summary' => 'Le rendez-vous s\'est terminé avant que le commercial ne prenne la parole.', 'next_step' => false, 'outcome' => 'Rien n\'a été engagé.', 'result' => 'Appel raccroché sans un mot', 'objectives' => array_map(static fn (string $o): array => ['objective' => $o, 'status' => 'manqué', 'evidence' => ''], $training->scenario->objectiveList()), 'goal_reached' => [] === $training->scenario->objectiveList() ? null : 'manqué', 'missed' => [], 'best' => [], 'worst' => [], 'tips' => ['Ouvrez le rendez-vous en reprenant le contexte et en demandant au client ce qu\'il attend de l\'échange.'], 'brief' => self::brief($training)];
    }

    /**
     * Une citation n'est gardée que si le commercial l'a vraiment dite (à la ponctuation et à la casse près).
     *
     * @param list<string> $lines
     */
    private static function said(string $quote, array $lines): bool
    {
        $norm = static fn (string $s): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)));
        $q = $norm($quote);
        if (mb_strlen($q) < 4) {
            return false;
        }
        foreach ($lines as $line) {
            $l = $norm($line);
            if (str_contains($l, $q) || (str_contains($q, $l) && mb_strlen($l) > 10)) {
                return true;
            }
            similar_text($q, $l, $percent);
            if ($percent >= 80) {
                return true;
            }
        }

        return false;
    }

    private static function str(mixed $v): string
    {
        return \is_scalar($v) ? trim((string) $v) : '';
    }
}
