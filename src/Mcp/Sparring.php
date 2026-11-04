<?php

namespace App\Mcp;

use App\Entity\Scenario;
use App\Entity\Training;
use App\Game\Situations;
use App\Training\Coach;
use App\Training\Progress;
use App\Training\ScenarioWriter;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

/**
 * Ce que l'IA du commercial peut faire pour lui : choisir une mise en situation, la jouer en relayant ses mots,
 * obtenir le débrief, préparer un vrai rendez-vous. Le brief caché du persona ne sort jamais avant le débrief.
 */
final readonly class Sparring
{
    public function __construct(
        private Me $me,
        private Trainer $trainer,
        private ScenarioWriter $writer,
        private Progress $progress,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param string $category decouverte, objection, negociation, mecontent, presse ou closing (vide : toutes)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'scenarios', title: 'Les mises en situation', description: 'Liste les scénarios d\'entraînement disponibles : titre, type de rendez-vous, difficulté, durée, contexte, objectif et persona (jamais son brief caché). Pour en jouer un : start_sparring avec son id.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function scenarios(string $category = ''): array
    {
        $seller = $this->me->seller();
        $criteria = ['active' => true, 'situation' => null, 'workspace' => $seller->workspace] + (isset(Scenario::CATEGORIES[$category]) ? ['category' => $category] : []);

        return ['scenarios' => array_map(static fn (Scenario $s): array => $s->public(), $this->em->getRepository(Scenario::class)->findBy($criteria, ['difficulty' => 'ASC', 'title' => 'ASC']))];
    }

    /**
     * @param string $type une situation précise (sa clé, voir my_progress ou la page « L'offre »), ou vide pour la surprise
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'new_call', title: 'Nouvel appel (situation surprise)', description: 'Le jeu : tire au hasard une situation d\'appel du catalogue de la société (demande de prix, appel à froid, client mécontent, suivi client, relance de devis, renouvellement…), l\'écrit pour le commercial (une dizaine de secondes, difficulté selon son niveau) et lance l\'appel. Montre-lui la fiche (situation, document, objectifs, interlocuteur) et la première phrase du persona ; ensuite, relaie ses répliques avec reply, puis end_sparring quand il raccroche. Ne choisis type que si le commercial le demande.')]
    public function newCall(string $type = ''): array
    {
        $seller = $this->me->seller();
        $current = $this->trainer->current($seller);
        if (null !== $current && $current->isLive()) {
            $this->trainer->abandon($current);
        }
        try {
            $recent = $this->progress->recent($seller);
            $scenario = $this->writer->situation(Situations::draw($seller->space(), $this->progress->of($seller)['level']['number'], '' !== $type ? $type : null, $recent['keys']), $seller, $recent['names']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ToolCallException('La situation n\'a pas pu être écrite : '.$e->getMessage().' Réessayez.');
        }
        $this->em->persist($scenario);
        $this->em->flush();
        $training = $this->trainer->start($seller, $scenario);

        return [
            'session' => $training->id,
            'call' => $scenario->public(),
            'minutes' => $scenario->minutes,
            'persona_says' => $scenario->opening,
            'note' => 'Montre la fiche au commercial (la situation, le document tel quel, ses objectifs, qui il appelle ou qui l\'appelle), puis la réplique du persona. Relaie ensuite ses mots, tels quels, avec reply.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'start_sparring', title: 'Lancer un sparring', description: 'Lance une session d\'entraînement sur un scénario (son id, donné par scenarios). Présente au commercial le contexte, son objectif, la durée, puis la première phrase du persona ; ensuite, relaie chacune de ses répliques avec reply. Une session déjà en cours est abandonnée.')]
    public function start(int $scenario): array
    {
        $seller = $this->me->seller();
        $s = $this->em->find(Scenario::class, $scenario);
        if (null === $s || !$s->visibleTo($seller)) {
            throw new ToolCallException('Aucun scénario n°'.$scenario.' : scenarios les liste.');
        }
        $current = $this->trainer->current($seller);
        if (null !== $current && $current->isLive()) {
            $this->trainer->abandon($current);
        }
        try {
            $training = $this->trainer->start($seller, $s);
        } catch (\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage());
        }

        return [
            'session' => $training->id,
            'scenario' => $s->public(),
            'minutes' => $s->minutes,
            'persona_says' => $s->opening,
            'note' => 'Montre au commercial le contexte, l\'objectif et la réplique du persona. Puis relaie ses mots, tels quels, avec reply.',
        ];
    }

    /**
     * @param string $message ce que dit le commercial, mot pour mot
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'reply', title: 'Répondre au persona', description: 'Transmet au persona la réplique du commercial, mot pour mot (ne la reformule pas, ne l\'améliore pas), et rend la réponse du persona à montrer telle quelle, le temps restant et le nombre de rubriques du brief découvertes. Quand la session s\'arrête (temps écoulé, persona qui part), propose end_sparring pour le débrief.')]
    public function reply(string $message, int $session = 0): array
    {
        $training = $this->me->training($session);
        try {
            $r = $this->trainer->say($training, $message);
        } catch (\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage().($training->isLive() ? '' : ' Le débrief : end_sparring.'));
        }

        return [
            'persona_says' => $r['reply'],
            'seconds_left' => $r['secondsLeft'],
            'discovered' => $r['revealed'].' / '.\count(Coach::brief($training)),
            'ended' => $r['ended'],
        ] + ($r['ended'] ? ['note' => match ($r['endedBy']) {
            'persona' => 'Le persona a mis fin au rendez-vous. Appelle end_sparring pour le débrief.',
            default => 'Le temps est écoulé. Appelle end_sparring pour le débrief.',
        }] : []);
    }

    /**
     * @param bool $abandon true pour abandonner sans débrief noté (le brief caché est tout de même dévoilé)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'end_sparring', title: 'Terminer et débriefer', description: 'Termine la session en cours et rédige le débrief noté (prend environ une minute) ; abandon=true l\'abandonne sans note. Ensuite, montre le débrief avec l\'app debrief.')]
    public function end(bool $abandon = false, int $session = 0): array
    {
        $training = $this->me->training($session);
        if ($abandon) {
            if ($training->isLive()) {
                $this->trainer->abandon($training);
            }

            return ['session' => $training->id, 'status' => $training->status, 'brief' => Coach::brief($training)];
        }
        if (Training::ABANDONED === $training->status) {
            throw new ToolCallException('Cette session a été abandonnée : pas de débrief noté.');
        }
        $this->trainer->finish($training);

        return self::summary($training);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'my_progress', title: 'Ma progression', description: 'L\'historique et la progression du commercial : ses missions données par son manager (pour en jouer une : new_call avec son situation_key), nombre de sessions, note moyenne et tendance, moyenne par critère, point fort et point faible, dernières sessions.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function progress(): array
    {
        $seller = $this->me->seller();
        $stats = $this->progress->of($seller);

        $missions = array_map(static fn (array $m): array => [
            'calls' => $m['mission']->calls, 'done' => $m['done'], 'target_score' => $m['mission']->target,
            'situation' => null !== $m['mission']->situation ? ($seller->space()->situations[$m['mission']->situation]['label'] ?? $m['mission']->situation) : 'au choix',
            'situation_key' => $m['mission']->situation, 'due' => $m['mission']->dueAt?->format('Y-m-d'), 'note' => $m['mission']->note, 'complete' => $m['complete'],
        ], $this->progress->missions($seller));

        return ['seller' => $seller->name, 'company' => $seller->space()->name, 'situations' => array_map(static fn (array $s): string => $s['label'], $seller->space()->situations), 'missions' => $missions] + $stats + ['recent' => array_map(static fn (Training $t): array => ['session' => $t->id, 'scenario' => $t->scenario->title, 'date' => $t->startedAt->format('Y-m-d H:i'), 'status' => $t->status, 'score' => $t->score], $this->progress->history($seller, 10))];
    }

    /**
     * @param string $prospect   la fiche prospect, ou la situation à simuler (entreprise, interlocuteur, contexte, ce qu'on sait)
     * @param string $category   decouverte, objection, negociation, mecontent, presse ou closing (vide : au choix du générateur)
     * @param int    $difficulty 1 (accessible), 2 (exigeant) ou 3 (coriace)
     * @param int    $minutes    durée maximale de la session
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'create_scenario', title: 'Créer un scénario depuis une fiche prospect', description: 'Fabrique une mise en situation depuis une fiche prospect ou une situation décrite, pour préparer un vrai rendez-vous : persona, contexte, objectif, et un brief caché crédible que le commercial devra découvrir (il ne t\'est pas montré). Prend environ une minute. Ensuite : start_sparring avec son id.')]
    public function create(string $prospect, string $category = '', int $difficulty = 2, int $minutes = 12): array
    {
        $seller = $this->me->seller();
        try {
            $scenario = $this->writer->write($prospect, $seller, $category, $difficulty, $minutes);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ToolCallException($e->getMessage());
        }
        $this->em->persist($scenario);
        $this->em->flush();

        return ['scenario' => $scenario->public(), 'note' => 'Le brief caché est scellé côté serveur. Pour jouer : start_sparring avec scenario='.$scenario->id.'.'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Training $training): array
    {
        $d = $training->debrief ?? [];

        return [
            'session' => $training->id,
            'scenario' => $training->scenario->title,
            'score' => $training->score,
            'criteria' => array_map(static fn (array $c): string => $c['score'].'/10 — '.$c['comment'], $d['criteria'] ?? []),
            'result' => $d['result'] ?? '',
            'goal_reached' => $d['goal_reached'] ?? null,
            'objectives' => array_map(static fn (array $o): string => $o['status'].' — '.$o['objective'], $d['objectives'] ?? []),
            'summary' => $d['summary'] ?? '',
            'next_step_obtained' => $d['next_step'] ?? false,
            'tips' => $d['tips'] ?? [],
            'note' => 'Montre le débrief complet (brief caché, répliques citées) avec l\'app debrief.',
        ];
    }
}
