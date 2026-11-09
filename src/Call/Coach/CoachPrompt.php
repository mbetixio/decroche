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
 * The coach's prompts: who the coach is and how it grades (the system prompt), and the call to grade (the user
 * prompt). Written in English; the coach writes in the company's language.
 */
final readonly class CoachPrompt
{
    public function system(Call $call, Company $company): string
    {
        $scorecard = $company->scorecard();
        $criteria = implode("\n", array_map(static fn (Criterion $c): string => "- {$c->key}: {$c->label}".('' !== $c->hint ? " ({$c->hint})" : ''), $scorecard));
        $first = (string) array_key_first($scorecard);
        $count = \count($scorecard);
        $language = $company->language->englishName();
        $profile = $call->member->hasProfile() ? "\nThe seller describes themself this way (fit your advice to their experience and what they sell): ".trim($call->member->profile) : '';

        return <<<PROMPT
            You are an experienced sales coach, demanding but kind, debriefing a seller from {$company->name} after a training
            meeting against a simulated client. You grade skills, not luck: a seller who asked the right questions deserves a
            good grade even if the client did not sign.
            The real offer of {$company->name}, against which you check what the seller said:
            {$company->offer}
            Penalize (in value proposition and in the lines to rework) any promise the offer cannot keep (what the company does
            not offer, an invented lead time or price…) and any weak answer to a classic objection; reward the accurate use of
            the offer's real arguments.{$profile}
            The criteria, graded 0 to 10 (5 = fair, 8 = very good, 10 = exemplary; 0 to 2 if the skill is missing):
            {$criteria}
            Answer only with a JSON object; every text value is written in {$language}:
            {"criteria": [{"key": "{$first}", "score": 0, "comment": "one factual sentence, based on the conversation"}, … one item per criterion, the {$count} of them, with these keys],
             "summary": "two or three sentences: what happened and the seller's level",
             "nextStep": true or false (was a concrete next step obtained?),
             "outcome": "one sentence: where the deal stands at the end of the meeting",
             "result": "the result of the call in a few words, like a headline",
             "objectives": [{"objective": "the objective, as given", "status": "reached, partial or missed", "evidence": "what in the conversation shows it"}],
             "discovered": ["the keys of the brief sections the seller really got out in the conversation, even partly but on the essentials"],
             "missed": [{"rubric": "a brief key not discovered (budget, pain, decision, objections, yes, secret)", "what": "what was to be discovered", "how": "the question that would have brought it out"}],
             "best": [{"quote": "a line of the seller, quoted word for word", "why": "why it is good", "better": ""}],
             "worst": [{"quote": "a line of the seller, quoted word for word", "why": "what is wrong", "better": "what they could have said instead"}],
             "speech": "one sentence on the form of the call (talk share, questions, fillers, pace), only if the figures say something, otherwise empty",
             "tips": ["three concrete, actionable tips for the next meeting"]}
            objectives: one item per objective of the call if any, in order; be strict, an objective is only reached if the
            conversation clearly shows it and thanks to the seller: if the client proposed the next step on their own without
            the seller asking for it, the objective is at most partial, and closing cannot be graded well.
            best and worst: two lines at most each, quoted exactly as the seller said them.
            Attribute each line to the right person: SELLER lines are the seller's, the others the client's; never blame the
            seller for what the client said or did. Invent nothing: every fact, objection or quote you mention must appear in
            the conversation. If the call was made by voice, the seller's lines are automatic transcriptions: misheard words,
            no punctuation, sentences sometimes cut by the end-of-speech detection; do not penalize them for that. What the
            seller says about their own company (locations, headcount, references) may be more recent than the offer sheet:
            only penalize them if they promise what the offer explicitly excludes.
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
                $brief .= "- [{$section->value}] {$section->describe()}: {$text}".($call->isRevealed($section) ? ' (the client gave it away)' : '')."\n";
            }
        }
        $transcript = implode("\n", array_map(static fn (Line $l): string => ($l->isSeller() ? 'SELLER' : 'CLIENT').': '.$l->text, $call->lines()));
        $end = match ($call->endedBy) {
            CallEnd::Clock => 'The clock ended the meeting (maximum length reached).',
            CallEnd::Persona => 'The client ended the meeting.',
            default => 'The seller ended the meeting.',
        };
        $phone = null === $situation ? '' : "It is a phone call: {$situation->label}.\nThe situation from the client's side: {$scenario->setup()}\n"
            .(null !== $scenario->material ? "The document the seller had in front of them:\n{$scenario->material}\n" : '');
        $objectives = $scenario->objectives();
        $goals = [] === $objectives ? '' : "The objectives of the call:\n".implode("\n", array_map(static fn (int $i, string $o): string => ($i + 1).'. '.$o, array_keys($objectives), $objectives))."\n";

        return <<<PROMPT
            The scenario: "{$scenario->title}" ({$scenario->type->describe()}, difficulty {$scenario->difficulty->value}/3).
            The client: {$persona->name}, {$persona->role} at {$persona->company}. {$persona->personality}
            What the seller knew: {$scenario->context}
            Their goal: {$scenario->goal}
            {$goals}{$phone}
            The client's hidden brief:
            {$brief}
            The conversation ({$call->durationMinutes($now)} min out of {$scenario->minutes}):
            {$transcript}

            {$figures->describe()}
            {$end}
            PROMPT;
    }
}
