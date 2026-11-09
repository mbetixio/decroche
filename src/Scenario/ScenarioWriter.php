<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Ai\Output\CallScenario;
use App\Ai\Output\MeetingScenario;
use App\Entity\Company;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Criterion;
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
     * @param list<string>   $avoid names (people, companies) the seller met recently, not to be used again
     * @param Criterion|null $focus the skill the seller wants to train: the situation is built to test it
     *
     * @throws ModelException when the model's answer cannot make a scenario
     */
    public function surprise(Draw $draw, Member $author, array $avoid = [], ?Criterion $focus = null): Scenario
    {
        $company = $this->companies->get();
        $situation = $draw->situation;
        $language = $company->language->englishName();
        $objectives = implode(' ; ', $situation->objectives);
        $material = '' !== $situation->material ? $situation->material : 'the relevant document';
        $system = <<<PROMPT
            You write phone call situations to train the sellers of {$company->name}, like a game: each situation is unique,
            realistic, precise (names, figures, dates, tools), with real sales tension.
            {$this->offer($company)}{$this->profile($author)}
            A simulated persona will play the other party on the phone: they will have a hidden brief that the seller must
            discover through questions. The title, context, document and objectives are shown to the seller: they must reveal
            nothing of the brief (neither the real pain, nor the hidden information, nor the real budget).
            The persona's attitude must make sense with the situation and with what {$company->name} sells: someone who calls,
            or who asked to be called back, is interested in it; their doubts are real but come out later in the call, when
            the seller pitches, quotes or asks the right question, never as a greeting.
            Answer only with a JSON object; every text value is written in {$language}:
            {"title": "a short, telling title, as the seller sees it",
             "context": "what the seller knows before the call, addressing them as you, 2 to 4 sentences: {$this->direction($situation)}",
             "material": "the document the seller has in front of them ({$material}), written in full like the real document, 5 to 15 lines, with line breaks",
             "goal": "the goal of the call in one sentence",
             "objectives": ["three concrete, checkable objectives for this call, adapted from: {$objectives}"],
             "persona": {"name": "first name last name", "gender": "f or m", "role": "job title", "company": "company name, sector, size, city", "personality": "2 or 3 sentences: their character on the phone (in the given tone), how they speak, what matters to them, a human trait (an anecdote, a passion, a habit)"},
             "opening": "{$this->openingHint($situation)}",
             "brief": {"setup": "the situation from the persona's side: what they know of the seller's company, their relationship with the seller, what they are doing when the call happens, their mood",
                       {$this->briefFields()}}}
            PROMPT;
        $user = 'Today is '.$this->clock->now()->format('Y-m-d')." (the scenario's dates, budgets and deadlines start from there).\n"
            ."The situation: {$situation->label}.\n{$situation->pitch}\n\n"
            ."The random dressing: {$draw->size}, sector: {$draw->sector}, in {$draw->city}. The persona is {$draw->tone->describe()}: a believable, natural person, not a caricature.\n"
            .$this->focus($focus)
            .([] !== $avoid ? 'Invent new names: use none of these names of people or companies, met recently: '.implode(', ', $avoid).".\n" : '')
            .\sprintf('Difficulty: %d/3.', $draw->difficulty->value);

        $data = $this->model->structured($system, $user, CallScenario::class, 1.0)->toArray();
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
            throw new \InvalidArgumentException('scenario.error.too_short');
        }
        $company = $this->companies->get();
        $language = $company->language->englishName();
        $types = implode(', ', array_map(static fn (MeetingType $t): string => "{$t->value} ({$t->describe()})", MeetingType::cases()));
        $system = <<<PROMPT
            You write role-play scenarios to train the sellers of {$company->name} for difficult meetings.
            {$this->offer($company)}{$this->profile($author)}
            A simulated persona will play the client: they will have a hidden brief that the seller must discover through
            questions. Write a realistic brief, precise, with figures where useful, consistent with the given situation, with
            real sales tension. The title, context and goal are public: they must reveal nothing of the brief (neither the real
            pain, nor the hidden information, nor the budget); the title describes the situation as the seller sees it.
            Answer only with a JSON object; every text value is written in {$language}:
            {"title": "a short, telling title",
             "type": "one of: {$types}",
             "context": "what the seller knows going in, addressing them as you, 2 to 4 sentences",
             "goal": "what they must obtain for the meeting to be a success (one sentence)",
             "persona": {"name": "first name last name", "gender": "f or m", "role": "job title", "company": "company and sector, size", "personality": "2 sentences on how they behave in a meeting"},
             "opening": "the first sentence the client says when greeting the seller",
             "brief": {{$this->briefFields()}}}
            PROMPT;
        $user = ($fromCall
            ? "What follows is the transcript of a real call (the client and the seller; turns are sometimes badly split). Rebuild this client so the seller can replay the call and do better: their company, job, character, way of speaking, objections, real stakes; the hidden brief starts from what they said and what they implied without saying it; the opening is theirs; the context describes the situation at the time of the call, revealing nothing of the brief. Keep their first name and company name if they appear.\n\nThe transcript:\n{$text}\n\n"
            : "The situation or the prospect file:\n{$text}\n\n")
            .(null !== $type ? "Meeting type: {$type->describe()}.\n" : '')
            .\sprintf('Difficulty: %d/3.', $difficulty->value);

        $data = $this->model->structured($system, $user, MeetingScenario::class, 0.9)->toArray();

        return ScenarioFactory::fromArray(['difficulty' => $difficulty->value, 'minutes' => $minutes] + (null !== $type ? ['type' => $type->value] : []) + $data, $author, $this->clock->now());
    }

    private function offer(Company $company): string
    {
        return "The offer of {$company->name}, to be respected (situations, needs and objections must match what {$company->name} really sells):\n{$company->offer}";
    }

    /**
     * The seller who will play, as they describe themself: the situation fits their job, without saying so.
     */
    private function profile(Member $author): string
    {
        return $author->hasProfile()
            ? "\nThe seller who will play describes themself this way: ".trim($author->profile)."\nFit the situation to their job (the offers they sell, their clients, their level), without mentioning it."
            : '';
    }

    /**
     * The skill the seller asked to work on, after a debrief: the situation must make it decisive, without saying so.
     */
    private function focus(?Criterion $focus): string
    {
        if (null === $focus) {
            return '';
        }
        $hint = '' !== $focus->hint ? " ({$focus->hint})" : '';

        return "The seller wants to train one skill, graded low in their last debrief: {$focus->label}{$hint}. Build the situation, "
            .'the persona and the brief so that this skill decides how the call goes: without it, the seller cannot reach the '
            ."objectives; with it, they can. The objectives test it. The public texts do not name the skill.\n";
    }

    private function direction(Situation $situation): string
    {
        return $situation->isIncoming()
            ? 'the client calls the seller (they call you, never you call them)'
            : 'the seller makes the call (you call them)';
    }

    private function openingHint(Situation $situation): string
    {
        // The first line opens a real call: who it is and why they call, nothing more. Objections, fears and past bad
        // experiences belong to the hidden brief and come out later, when the seller asks or pitches.
        return $situation->isIncoming()
            ? "the persona's first line as they call the seller: in one or two short sentences, who they are and why they are calling, in their mood and as interested as someone who chose to call; no objection, doubt, fear or past bad experience yet, nothing from the hidden brief"
            : "the persona's first line as they pick up the phone without knowing who is calling: their first name or their company, in their mood, and nothing more";
    }

    private function briefFields(): string
    {
        return implode(', ', array_map(static fn (BriefSection $s): string => "\"{$s->value}\": \"{$s->prompt()}\"", BriefSection::cases()));
    }
}
