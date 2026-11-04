<?php

namespace App\Training;

use App\Ai\Brain;
use App\Entity\Scenario;
use App\Entity\Seller;
use App\Game\Situations;

/**
 * La génération assistée : une idée, ou la fiche d'un vrai prospect, devient une mise en situation complète, avec un
 * brief caché crédible.
 */
final readonly class ScenarioWriter
{
    public function __construct(
        private Brain $brain,
    ) {
    }

    /**
     * @param bool $call l'idée est la transcription d'un vrai appel : le client est reconstitué tel qu'il était
     */
    public function write(string $idea, ?Seller $author = null, string $category = '', int $difficulty = 2, int $minutes = 12, bool $call = false): Scenario
    {
        $idea = mb_substr(trim($idea), 0, 20000);
        if (mb_strlen($idea) < 15) {
            throw new \InvalidArgumentException('Décrivez la situation ou collez la fiche prospect (au moins une phrase).');
        }
        $categories = implode(', ', array_map(static fn (string $k, string $l): string => "{$k} ({$l})", array_keys(Scenario::CATEGORIES), Scenario::CATEGORIES));
        $category = isset(Scenario::CATEGORIES[$category]) ? $category : '';
        $difficulty = max(1, min(3, $difficulty));
        $system = <<<PROMPT
            Tu écris des mises en situation pour entraîner les commerciaux de {$this->company($author)} aux rendez-vous difficiles.
            {$this->offer($author)}{$this->profile($author)}
            Un interlocuteur simulé jouera le client : il aura un brief caché que le commercial doit découvrir par ses
            questions. Écris un brief réaliste, précis, chiffré quand c'est utile, cohérent avec la situation donnée, avec
            une vraie tension commerciale. Le titre, le contexte et l'objectif sont publics : ils ne doivent rien dévoiler du brief
            (ni la vraie douleur, ni l'information cachée, ni le budget) ; le titre décrit la situation telle que le commercial la voit.
            Réponds uniquement avec un objet JSON, en français :
            {"title": "titre court et parlant",
             "category": "une de : {$categories}",
             "context": "ce que le commercial sait en arrivant, en le vouvoyant (« Vous rencontrez… »), 2 à 4 phrases",
             "goal": "ce qu'il doit obtenir pour réussir le rendez-vous (une phrase)",
             "persona": {"name": "prénom nom", "role": "fonction", "company": "entreprise et secteur, taille", "personality": "2 phrases sur sa façon d'être en rendez-vous"},
             "opening": "la première phrase que dit le client en accueillant le commercial",
             "brief": {"budget": "le budget réel et ses contraintes", "pain": "la vraie douleur, celle qu'il ne dit pas d'emblée", "decision": "qui décide vraiment, le processus, le calendrier", "objections": "2 ou 3 objections qu'il sortira", "yes": "ce qui le ferait dire oui à une prochaine étape", "secret": "une information cachée qui change la donne"}}
            PROMPT;
        $user = ($call
            ? "Ce qui suit est la transcription d'un vrai appel (le client et le commercial ; les tours de parole sont parfois mal séparés). Reconstitue ce client pour que le commercial puisse rejouer l'appel et faire mieux : sa société, sa fonction, son caractère, sa façon de parler, ses objections, ses enjeux réels ; le brief caché part de ce qu'il a dit et de ce qu'il a laissé entendre sans le dire ; l'ouverture reprend la sienne ; le contexte décrit la situation au moment de l'appel, sans rien dévoiler du brief. Garde son prénom et le nom de sa société s'ils y sont.\n\nLa transcription :\n{$idea}\n\n"
            : "La situation ou la fiche prospect :\n{$idea}\n\n")
            .('' !== $category ? 'Type de rendez-vous : '.Scenario::CATEGORIES[$category].".\n" : '')
            ."Difficulté : ".Scenario::DIFFICULTIES[$difficulty].' ('.$difficulty.'/3).';
        $d = $this->brain->json($system, $user, 0.9);

        return self::fromArray($d + ['difficulty' => $difficulty, 'minutes' => $minutes] + ('' !== $category ? ['category' => $category] : []), $author);
    }

    /**
     * Une situation d'appel tirée au hasard (le jeu) : l'IA écrit l'entreprise, l'interlocuteur, le document que le
     * commercial a sous les yeux, les objectifs de l'appel et le brief caché.
     *
     * @param array{key: string, sector: string, size: string, city: string, mood: string, tone?: string, difficulty: int} $draw
     * @param list<string>                                                                                      $avoid les noms (personnes, entreprises) déjà vus récemment par ce commercial
     */
    public function situation(array $draw, Seller $author, array $avoid = []): Scenario
    {
        $sit = $author->space()->situation($draw['key']) ?? Situations::get($draw['key']) ?? throw new \InvalidArgumentException('Situation inconnue.');
        $incoming = 'entrant' === $sit['direction'];
        $objectives = implode(' ; ', $sit['objectives']);
        $system = <<<PROMPT
            Tu écris des situations d'appel téléphonique pour entraîner les commerciaux de {$this->company($author)}, comme un jeu : chaque
            situation est unique, réaliste, précise (noms, chiffres, dates, outils), avec une vraie tension commerciale.
            {$this->offer($author)}{$this->profile($author)}
            Un interlocuteur simulé jouera le persona au téléphone : il aura un brief caché que le commercial doit découvrir
            par ses questions. Le titre, le contexte, le document et les objectifs sont montrés au commercial : ils ne
            doivent rien dévoiler du brief (ni la vraie douleur, ni l'information cachée, ni le budget réel).
            Réponds uniquement avec un objet JSON, en français :
            {"title": "titre court et parlant, vu du commercial",
             "context": "ce que le commercial sait avant l'appel, en le vouvoyant, 2 à 4 phrases : {$this->direction($incoming)}",
             "material": "le document que le commercial a sous les yeux ({$sit['material']}), rédigé en entier comme le vrai document, 5 à 15 lignes, avec des sauts de ligne",
             "goal": "le but de l'appel en une phrase",
             "objectives": ["trois objectifs concrets et vérifiables pour cet appel, adaptés de : {$objectives}"],
             "persona": {"name": "prénom nom", "gender": "f ou m", "role": "fonction", "company": "nom de l'entreprise, secteur, taille, ville", "personality": "2 ou 3 phrases : son caractère au téléphone (dans le ton donné), sa façon de parler, ce qui compte pour lui, un trait humain (une anecdote, une passion, une manie)"},
             "opening": "{$this->openingHint($incoming)}",
             "brief": {"setup": "la situation vue du persona : ce qu'il sait de la société du commercial, sa relation avec le commercial, ce qu'il fait au moment de l'appel, son humeur",
                       "budget": "le budget réel et ses contraintes", "pain": "la vraie douleur, qu'il ne dit pas d'emblée", "decision": "qui décide vraiment, le processus, le calendrier",
                       "objections": "2 ou 3 objections qu'il sortira", "yes": "ce qui le ferait dire oui à la prochaine étape", "secret": "une information cachée qui change la donne"}}
            PROMPT;
        $user = 'Nous sommes le '.(new \DateTimeImmutable())->format('d/m/Y')." (les dates, budgets et échéances du scénario partent de là).\n"
            ."La situation : {$sit['label']}.\n{$sit['pitch']}\n\n"
            ."L'habillage tiré au sort : {$draw['size']} du secteur {$draw['sector']}, à {$draw['city']}. L'interlocuteur est {$draw['mood']} : une personne crédible et naturelle, pas une caricature.\n"
            .([] !== $avoid ? 'Invente des noms neufs : n\'emploie aucun de ces noms de personnes ou d\'entreprises, déjà vus récemment : '.implode(', ', $avoid).".\n" : '')
            .'Difficulté : '.Scenario::DIFFICULTIES[$draw['difficulty']].' ('.$draw['difficulty'].'/3).';
        $d = $this->brain->json($system, $user, 1.0);
        $scenario = self::fromArray($d + ['difficulty' => $draw['difficulty'], 'minutes' => [1 => 8, 2 => 10, 3 => 12][$draw['difficulty']] ?? 10, 'category' => self::category($draw['key'], $sit['family'])], $author);
        $scenario->situation = $draw['key'];
        if (isset($draw['tone'])) {
            $scenario->persona['tone'] = $draw['tone'];
        }
        $scenario->objectives ??= $sit['objectives'];

        return $scenario;
    }

    private function direction(bool $incoming): string
    {
        return $incoming
            ? 'c\'est le client qui appelle le commercial (« … vous appelle », jamais « vous appelez »)'
            : 'c\'est le commercial qui appelle (« Vous appelez… »)';
    }

    private function openingHint(bool $incoming): string
    {
        return $incoming
            ? 'la première phrase du persona, qui appelle le commercial (il se présente et attaque le sujet, dans son humeur)'
            : 'la première phrase du persona qui décroche le téléphone sans savoir qui l\'appelle (« Allô, [entreprise], [prénom] ? » ou équivalent, dans son humeur)';
    }

    private static function category(string $situation, string $family = ''): string
    {
        return match (true) {
            \in_array($situation, ['client_mecontent', 'resiliation', 'depart_dev'], true), (bool) preg_match('/mecontent|resili|plainte|litige|arret/', $situation) => 'mecontent',
            \in_array($situation, ['renouvellement', 'concurrent', 'forfait_regie'], true) => 'negociation',
            'echaude_offshore' === $situation || str_contains($situation, 'objection') => 'objection',
            'relance_devis' === $situation => 'closing',
            \in_array($situation, ['formulaire_prix', 'levee_fonds', 'urgence', 'demande_prix'], true) => 'presse',
            'negociation' === $family => 'negociation',
            default => 'decouverte',
        };
    }

    /**
     * Un scénario depuis un tableau (la bibliothèque de départ, une génération).
     *
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d, ?Seller $author = null): Scenario
    {
        $s = static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '';
        $p = \is_array($d['persona'] ?? null) ? $d['persona'] : [];
        $b = \is_array($d['brief'] ?? null) ? $d['brief'] : [];
        $persona = ['name' => $s($p['name'] ?? ''), 'role' => $s($p['role'] ?? ''), 'company' => $s($p['company'] ?? ''), 'personality' => $s($p['personality'] ?? '')]
            + (\in_array(mb_strtolower($s($p['gender'] ?? '')), ['f', 'm'], true) ? ['gender' => mb_strtolower($s($p['gender']))] : [])
            + (isset(\App\Game\Situations::TONES[$s($p['tone'] ?? '')]) ? ['tone' => $s($p['tone'])] : []);
        $brief = [];
        foreach ([...array_keys(Scenario::BRIEF), 'setup'] as $key) {
            $brief[$key] = \is_array($b[$key] ?? null) ? implode(' ; ', array_map($s, $b[$key])) : $s($b[$key] ?? '');
        }
        if ('' === $s($d['title'] ?? '') || '' === $persona['name'] || '' === $s($d['opening'] ?? '') || '' === $brief['pain']) {
            throw new \RuntimeException('Le scénario généré est incomplet : réessayez.');
        }
        $category = $s($d['category'] ?? '');

        $scenario = new Scenario(
            title: $s($d['title']),
            category: isset(Scenario::CATEGORIES[$category]) ? $category : 'decouverte',
            difficulty: max(1, min(3, (int) ($d['difficulty'] ?? 2))),
            minutes: max(3, min(60, (int) ($d['minutes'] ?? 12))),
            context: $s($d['context'] ?? ''),
            goal: $s($d['goal'] ?? ''),
            persona: $persona,
            opening: trim($s($d['opening']), " \t\n«»\"“”"),
            brief: array_filter($brief, static fn (string $v, string $k): bool => 'setup' !== $k || '' !== $v, \ARRAY_FILTER_USE_BOTH),
            author: $author,
        );
        $scenario->material = '' !== $s($d['material'] ?? '') ? $s($d['material']) : null;
        $objectives = array_values(array_filter(array_map($s, \is_array($d['objectives'] ?? null) ? $d['objectives'] : [])));
        $scenario->objectives = [] !== $objectives ? \array_slice($objectives, 0, 4) : null;

        return $scenario;
    }

    private function company(?Seller $author): string
    {
        return $author?->workspace->name ?? 'Etixio';
    }

    private function offer(?Seller $author): string
    {
        $company = $this->company($author);

        return "L'offre de {$company}, à respecter (les situations, besoins et objections doivent coller à ce que {$company} vend vraiment) :\n"
            .($author?->workspace->offer ?? \App\Game\Etixio::OFFER);
    }

    /**
     * Le commercial qui va jouer, tel qu'il se décrit : la situation s'adapte à son métier, sans le dire.
     */
    private function profile(?Seller $author): string
    {
        return null !== $author && $author->hasProfile()
            ? "\nLe commercial qui va jouer se décrit ainsi : ".trim($author->profile)."\nAdapte la situation à son métier (les offres qu'il vend, ses clients, son niveau), sans le mentionner."
            : '';
    }
}
