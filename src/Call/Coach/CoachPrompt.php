<?php

declare(strict_types=1);

namespace App\Call\Coach;

use App\Call\Speech\SpeechFigures;
use App\Entity\Call;
use App\Entity\Company;
use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Model\Criterion;
use App\Model\Line;

/**
 * The coach's prompts: who the coach is and how it grades (the system prompt), and the call to grade (the user prompt).
 */
final class CoachPrompt
{
    public function system(Call $call, Company $company): string
    {
        $scorecard = $company->scorecard();
        $criteria = implode("\n", array_map(static fn (Criterion $c): string => "- {$c->key} : {$c->label}".('' !== $c->hint ? " ({$c->hint})" : ''), $scorecard));
        $first = (string) array_key_first($scorecard);
        $count = \count($scorecard);
        $profile = $call->member->hasProfile() ? "\nLe commercial se décrit ainsi (adapte tes conseils à son expérience et à ce qu'il vend) : ".trim($call->member->profile) : '';

        return <<<PROMPT
            Tu es un coach commercial expérimenté, exigeant mais bienveillant, qui débriefe un commercial de {$company->name} après un
            rendez-vous d'entraînement contre un interlocuteur simulé. Tu notes les compétences, pas la chance : un commercial
            qui a posé les bonnes questions mérite une bonne note même si le client n'a pas signé.
            L'offre réelle {$company->of()}, à laquelle tu confrontes ce que le commercial a dit :
            {$company->offer}
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

    public function user(Call $call, SpeechFigures $figures, \DateTimeImmutable $now): string
    {
        $scenario = $call->scenario;
        $persona = $scenario->persona();
        $situation = $scenario->situation();
        $brief = '';
        foreach (BriefSection::cases() as $section) {
            if ('' !== $text = $scenario->brief($section)) {
                $brief .= "- [{$section->value}] {$section->label()} : {$text}".($call->isRevealed($section) ? ' (le client l\'a lâché)' : '')."\n";
            }
        }
        $transcript = implode("\n", array_map(static fn (Line $l): string => ($l->isSeller() ? 'COMMERCIAL' : 'CLIENT').' : '.$l->text, $call->lines()));
        $end = match ($call->endedBy) {
            CallEnd::Clock => 'L\'horloge a mis fin au rendez-vous (durée maximale atteinte).',
            CallEnd::Persona => 'Le client a mis fin au rendez-vous.',
            default => 'Le commercial a mis fin au rendez-vous.',
        };
        $phone = null === $situation ? '' : "C'est un appel téléphonique : {$situation->label}.\nLa situation vue du client : {$scenario->setup()}\n"
            .(null !== $scenario->material ? "Le document que le commercial avait sous les yeux :\n{$scenario->material}\n" : '');
        $objectives = $scenario->objectives();
        $goals = [] === $objectives ? '' : "Les objectifs de l'appel :\n".implode("\n", array_map(static fn (int $i, string $o): string => ($i + 1).'. '.$o, array_keys($objectives), $objectives))."\n";

        return <<<PROMPT
            Le scénario : « {$scenario->title} » ({$scenario->type->label()}, difficulté {$scenario->difficulty->label()}).
            Le client : {$persona->name}, {$persona->role} chez {$persona->company}. {$persona->personality}
            Ce que le commercial savait : {$scenario->context}
            Son objectif : {$scenario->goal}
            {$goals}{$phone}
            Le brief caché du client :
            {$brief}
            La conversation ({$call->durationMinutes($now)} min sur {$scenario->minutes}) :
            {$transcript}

            {$figures->describe()}
            {$end}
            PROMPT;
    }
}
