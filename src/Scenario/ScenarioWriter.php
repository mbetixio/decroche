<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Entity\Company;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Situation;
use App\Repository\CompanyRepository;
use Psr\Clock\ClockInterface;

/**
 * Asks the model to write a scenario: a surprise call from a drawn situation, or a rehearsal of a real meeting from a
 * prospect file or the transcript of a real call. The hidden brief is written along, never shown to the seller.
 */
final readonly class ScenarioWriter
{
    public function __construct(
        private LanguageModel $model,
        private CompanyRepository $companies,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A surprise call: the company, the person on the line, the document the seller has in front of them, the call's
     * objectives and the hidden brief, all new.
     *
     * @param list<string> $avoid names (people, companies) the seller met recently, not to be used again
     *
     * @throws ModelException when the model's answer cannot make a scenario
     */
    public function surprise(Draw $draw, Member $author, array $avoid = []): Scenario
    {
        $company = $this->companies->get();
        $situation = $draw->situation;
        $objectives = implode(' ; ', $situation->objectives);
        $system = <<<PROMPT
            Tu écris des situations d'appel téléphonique pour entraîner les commerciaux de {$company->name}, comme un jeu :
            chaque situation est unique, réaliste, précise (noms, chiffres, dates, outils), avec une vraie tension commerciale.
            {$this->offer($company)}{$this->profile($author)}
            Un interlocuteur simulé jouera le persona au téléphone : il aura un brief caché que le commercial doit découvrir
            par ses questions. Le titre, le contexte, le document et les objectifs sont montrés au commercial : ils ne
            doivent rien dévoiler du brief (ni la vraie douleur, ni l'information cachée, ni le budget réel).
            Réponds uniquement avec un objet JSON, en français :
            {"title": "titre court et parlant, vu du commercial",
             "context": "ce que le commercial sait avant l'appel, en le vouvoyant, 2 à 4 phrases : {$this->direction($situation)}",
             "material": "le document que le commercial a sous les yeux ({$situation->material}), rédigé en entier comme le vrai document, 5 à 15 lignes, avec des sauts de ligne",
             "goal": "le but de l'appel en une phrase",
             "objectives": ["trois objectifs concrets et vérifiables pour cet appel, adaptés de : {$objectives}"],
             "persona": {"name": "prénom nom", "gender": "f ou m", "role": "fonction", "company": "nom de l'entreprise, secteur, taille, ville", "personality": "2 ou 3 phrases : son caractère au téléphone (dans le ton donné), sa façon de parler, ce qui compte pour lui, un trait humain (une anecdote, une passion, une manie)"},
             "opening": "{$this->openingHint($situation)}",
             "brief": {"setup": "la situation vue du persona : ce qu'il sait de la société du commercial, sa relation avec le commercial, ce qu'il fait au moment de l'appel, son humeur",
                       {$this->briefFields()}}}
            PROMPT;
        $user = 'Nous sommes le '.$this->clock->now()->format('d/m/Y')." (les dates, budgets et échéances du scénario partent de là).\n"
            ."La situation : {$situation->label}.\n{$situation->pitch}\n\n"
            ."L'habillage tiré au sort : {$draw->size} du secteur {$draw->sector}, à {$draw->city}. L'interlocuteur est {$draw->tone->label()} : une personne crédible et naturelle, pas une caricature.\n"
            .([] !== $avoid ? 'Invente des noms neufs : n\'emploie aucun de ces noms de personnes ou d\'entreprises, déjà vus récemment : '.implode(', ', $avoid).".\n" : '')
            .\sprintf('Difficulté : %s (%d/3).', $draw->difficulty->label(), $draw->difficulty->value);

        $data = $this->model->json($system, $user, 1.0);
        $scenario = ScenarioFactory::fromArray([
            'difficulty' => $draw->difficulty->value,
            'minutes' => $draw->difficulty->minutes(),
            'type' => MeetingType::ofSituation($situation->key, $situation->family)->value,
        ] + $data, $author, $this->clock->now());
        $scenario->setSituation($situation);
        $scenario->setPersona($scenario->persona()->withTone($draw->tone));
        if ([] === $scenario->objectives()) {
            $scenario->setObjectives($situation->objectives);
        }

        return $scenario;
    }

    /**
     * A rehearsal of a real meeting, from a prospect file or a description; or, with $fromCall, from the transcript of
     * a real call, whose client is rebuilt as they were so the seller can replay it and do better.
     *
     * @throws \InvalidArgumentException when the text is too short to work from
     * @throws ModelException            when the model's answer cannot make a scenario
     */
    public function prepare(string $text, Member $author, ?MeetingType $type = null, Difficulty $difficulty = Difficulty::Demanding, int $minutes = 12, bool $fromCall = false): Scenario
    {
        $text = mb_substr(trim($text), 0, 20_000);
        if (mb_strlen($text) < 15) {
            throw new \InvalidArgumentException('Décrivez la situation ou collez la fiche prospect (au moins une phrase).');
        }
        $company = $this->companies->get();
        $types = implode(', ', array_map(static fn (MeetingType $t): string => "{$t->value} ({$t->label()})", MeetingType::cases()));
        $system = <<<PROMPT
            Tu écris des mises en situation pour entraîner les commerciaux de {$company->name} aux rendez-vous difficiles.
            {$this->offer($company)}{$this->profile($author)}
            Un interlocuteur simulé jouera le client : il aura un brief caché que le commercial doit découvrir par ses
            questions. Écris un brief réaliste, précis, chiffré quand c'est utile, cohérent avec la situation donnée, avec
            une vraie tension commerciale. Le titre, le contexte et l'objectif sont publics : ils ne doivent rien dévoiler du
            brief (ni la vraie douleur, ni l'information cachée, ni le budget) ; le titre décrit la situation telle que le
            commercial la voit.
            Réponds uniquement avec un objet JSON, en français :
            {"title": "titre court et parlant",
             "type": "une de : {$types}",
             "context": "ce que le commercial sait en arrivant, en le vouvoyant (« Vous rencontrez… »), 2 à 4 phrases",
             "goal": "ce qu'il doit obtenir pour réussir le rendez-vous (une phrase)",
             "persona": {"name": "prénom nom", "gender": "f ou m", "role": "fonction", "company": "entreprise et secteur, taille", "personality": "2 phrases sur sa façon d'être en rendez-vous"},
             "opening": "la première phrase que dit le client en accueillant le commercial",
             "brief": {{$this->briefFields()}}}
            PROMPT;
        $user = ($fromCall
            ? "Ce qui suit est la transcription d'un vrai appel (le client et le commercial ; les tours de parole sont parfois mal séparés). Reconstitue ce client pour que le commercial puisse rejouer l'appel et faire mieux : sa société, sa fonction, son caractère, sa façon de parler, ses objections, ses enjeux réels ; le brief caché part de ce qu'il a dit et de ce qu'il a laissé entendre sans le dire ; l'ouverture reprend la sienne ; le contexte décrit la situation au moment de l'appel, sans rien dévoiler du brief. Garde son prénom et le nom de sa société s'ils y sont.\n\nLa transcription :\n{$text}\n\n"
            : "La situation ou la fiche prospect :\n{$text}\n\n")
            .(null !== $type ? "Type de rendez-vous : {$type->label()}.\n" : '')
            .\sprintf('Difficulté : %s (%d/3).', $difficulty->label(), $difficulty->value);

        $data = $this->model->json($system, $user, 0.9);

        return ScenarioFactory::fromArray(['difficulty' => $difficulty->value, 'minutes' => $minutes] + (null !== $type ? ['type' => $type->value] : []) + $data, $author, $this->clock->now());
    }

    private function offer(Company $company): string
    {
        return "L'offre {$company->of()}, à respecter (les situations, besoins et objections doivent coller à ce que {$company->name} vend vraiment) :\n{$company->offer}";
    }

    /**
     * The seller who will play, as they describe themselves: the situation fits their job, without saying so.
     */
    private function profile(Member $author): string
    {
        return $author->hasProfile()
            ? "\nLe commercial qui va jouer se décrit ainsi : ".trim($author->profile)."\nAdapte la situation à son métier (les offres qu'il vend, ses clients, son niveau), sans le mentionner."
            : '';
    }

    private function direction(Situation $situation): string
    {
        return $situation->isIncoming()
            ? 'c\'est le client qui appelle le commercial (« … vous appelle », jamais « vous appelez »)'
            : 'c\'est le commercial qui appelle (« Vous appelez… »)';
    }

    private function openingHint(Situation $situation): string
    {
        return $situation->isIncoming()
            ? 'la première phrase du persona, qui appelle le commercial (il se présente et attaque le sujet, dans son humeur)'
            : 'la première phrase du persona qui décroche le téléphone sans savoir qui l\'appelle (« Allô, [entreprise], [prénom] ? » ou équivalent, dans son humeur)';
    }

    private function briefFields(): string
    {
        return implode(', ', array_map(static fn (BriefSection $s): string => "\"{$s->value}\": \"{$s->prompt()}\"", BriefSection::cases()));
    }
}
