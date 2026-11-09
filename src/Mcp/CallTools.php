<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Ai\ModelException;
use App\Call\CallException;
use App\Call\CallManager;
use App\Call\Coach\Debrief;
use App\Entity\Call;
use App\Entity\Scenario;
use App\Enum\CallStatus;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Situation;
use App\Repository\CompanyRepository;
use App\Repository\ScenarioRepository;
use App\Scenario\ScenarioWriter;
use App\Scenario\SurpriseCalls;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a seller's AI can do for them: draw a surprise call or prepare a real meeting, then either hand them a link to
 * take the call out loud in the browser, or relay their words right in the chat. The persona's hidden brief never
 * leaves the server before the debrief.
 */
final readonly class CallTools
{
    public function __construct(
        private CurrentMember $me,
        private CallManager $calls,
        private SurpriseCalls $surpriseCalls,
        private ScenarioWriter $writer,
        private ScenarioRepository $scenarios,
        private CompanyRepository $companies,
        private CallLinks $links,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'situations', title: 'The company\'s situations', description: 'Lists the kinds of calls the company\'s sellers run into (a price request, a cold call, an unhappy client, a renewal…), drawn from its offer. new_call draws one at random, or the one given by its key.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function situations(): array
    {
        $company = $this->companies->get();

        return [
            'company' => $company->name,
            'language' => $company->language->value,
            'situations' => array_values(array_map(static fn (Situation $s): array => [
                'key' => $s->key, 'label' => $s->emoji.' '.$s->label, 'family' => $s->family->value, 'direction' => $s->direction->value, 'objectives' => $s->objectives,
            ], $company->situations())),
        ];
    }

    /**
     * @param string $situation the key of a situation (see the situations tool), or empty for a surprise
     * @param string $mode      "link" (default): a link to take the call out loud in the browser; "chat": play it here, in writing
     * @param string $focus     a criterion of the scorecard to train on (its key, see my_progress or the scorecard resource): the call is built to test it
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'new_call', title: 'New call', description: 'The game: draws a call situation from the company\'s catalogue (at random unless a situation key is given), writes it for the seller at a difficulty that follows their latest scores (about fifteen seconds) and returns its briefing and a call link. With focus, the call is built to train one criterion of the scorecard. By default (mode "link"), give the seller the link: it opens the phone screen in their browser, already signed in, to take the call out loud with a voice. With mode "chat", the call starts here: show the briefing and the persona\'s first line, then relay the seller\'s words with reply.')]
    public function newCall(string $situation = '', string $mode = 'link', string $focus = ''): array
    {
        $member = $this->me->member();
        try {
            $scenario = $this->surpriseCalls->draw($member, '' !== $situation ? $situation : null, '' !== $focus ? $focus : null);
        } catch (ModelException|\RuntimeException $e) {
            // An unreachable model's error (an address, a response body) stays on the server.
            throw new ToolCallException($e instanceof ModelException ? 'The call could not be written ('.$e->getMessage().'). Try again.' : 'The model could not be reached. Try again in a moment.');
        }

        return $this->offer($scenario, $mode);
    }

    /**
     * @param string $prospect   what is known of the prospect or the meeting: company, person, context, what is at stake
     * @param string $type       discovery, objection, negotiation, unhappy_client, rushed or closing (empty: the writer picks)
     * @param int    $difficulty 1 (approachable), 2 (demanding) or 3 (tough)
     * @param int    $minutes    the call's maximum length
     * @param string $mode       "link" (default) or "chat", as for new_call
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'prepare_call', title: 'Rehearse a real meeting', description: 'Writes a rehearsal of a real meeting from a prospect sheet or a described situation: a persona, a context, a goal and a credible hidden brief the seller will have to uncover (it is not shown to you). Takes about a minute. Returns a call link (mode "link") or starts the call here (mode "chat").')]
    public function prepareCall(string $prospect, string $type = '', int $difficulty = 2, int $minutes = 12, string $mode = 'link'): array
    {
        $member = $this->me->member();
        try {
            $scenario = $this->writer->prepare($prospect, $member, MeetingType::tryFrom($type), Difficulty::tryFrom($difficulty) ?? Difficulty::Demanding, max(3, min(30, $minutes)));
        } catch (ModelException $e) {
            throw new ToolCallException('The rehearsal could not be written ('.$e->getMessage().'). Try again.');
        } catch (\InvalidArgumentException $e) {
            throw new ToolCallException($this->translator->trans($e->getMessage()));
        } catch (\RuntimeException) {
            throw new ToolCallException('The model could not be reached. Try again in a moment.');
        }
        $this->scenarios->save($scenario);

        return $this->offer($scenario, $mode);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'library', title: 'The scenario library', description: 'Lists the managers\' practice scenarios (title, type, difficulty, length, context, goal, persona; never the hidden brief). To play one: call_link with its id, or start_call to play it here.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function library(): array
    {
        return ['scenarios' => array_map(static fn (Scenario $s): array => $s->publicView(), $this->scenarios->library($this->me->member()))];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'call_link', title: 'Call link', description: 'A link that opens a scenario\'s briefing and phone screen in the seller\'s browser, already signed in (valid 15 minutes, a few uses).', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function callLink(int $scenario): array
    {
        return ['link' => $this->links->briefing($this->me->member(), $this->scenario($scenario)), 'valid_minutes' => CallLinks::LIFETIME / 60];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'start_call', title: 'Play a call in the chat', description: 'Starts a scenario here, in writing: show the seller the briefing and the persona\'s first line, then relay each of their lines with reply. A call already in progress is abandoned.')]
    public function startCall(int $scenario): array
    {
        return $this->start($this->scenario($scenario));
    }

    /**
     * @param string $message what the seller says, word for word
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'reply', title: 'Talk to the persona', description: 'Passes the seller\'s line to the persona word for word (never rephrase or improve it) and returns the persona\'s reply, to show as is, with the time left and how many sections of the hidden brief were uncovered. When the call stops (time up, the persona left), offer hang_up for the debrief.')]
    public function reply(string $message, int $call = 0): array
    {
        $current = $this->me->call($call);
        try {
            $turn = $this->calls->say($current, $message);
        } catch (CallException $e) {
            throw new ToolCallException($this->translator->trans($e->getMessage(), domain: 'messages', locale: 'en').($current->isLive() ? '' : ' Debrief it with hang_up.'));
        }

        return [
            'persona_says' => $turn->reply,
            'seconds_left' => $turn->secondsLeft,
            'uncovered' => $turn->revealed.' / '.\count($current->briefSheet()),
            'ended' => $turn->ended,
        ] + ($turn->ended ? ['note' => 'The call is over ('.$turn->endedBy?->value.'). Call hang_up for the debrief.'] : []);
    }

    /**
     * @param bool $abandon true to drop the call without a graded debrief (the hidden brief is still revealed)
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'hang_up', title: 'Hang up and debrief', description: 'Ends the call (the one in progress by default) and has the coach write the graded debrief (about a minute); abandon=true drops it without a grade. Then show it with the debrief app.')]
    public function hangUp(bool $abandon = false, int $call = 0): array
    {
        $current = $this->me->call($call);
        if ($abandon) {
            if ($current->isLive()) {
                $this->calls->abandon($current);
            }

            return ['call' => $current->id, 'status' => $current->status->value, 'brief' => self::brief($current)];
        }
        if (CallStatus::Abandoned === $current->status) {
            throw new ToolCallException('This call was abandoned: it has no graded debrief.');
        }

        return self::summary($this->calls->finish($current));
    }

    /**
     * The graded debrief in a few lines, for the client's model.
     *
     * @return array<string, mixed>
     */
    public static function summary(Call $call): array
    {
        $d = Debrief::fromArray($call->debrief ?? []);

        return [
            'call' => $call->id,
            'scenario' => $call->scenario->title,
            'score' => $call->score,
            'result' => $d->result,
            'criteria' => array_map(static fn (array $c): string => $c['score'].'/10 — '.$c['comment'], $d->criteria),
            'goal' => $d->goal?->value,
            'objectives' => array_map(static fn (array $o): string => $o['status']->value.' — '.$o['objective'], $d->objectives),
            'summary' => $d->summary,
            'next_step_obtained' => $d->nextStep,
            'tips' => $d->tips,
            'brief' => self::brief($call),
            'note' => 'Show the full debrief (the hidden brief, the quoted lines) with the debrief app.',
        ];
    }

    /**
     * @return list<array{section: string, text: string, uncovered: bool}>
     */
    private static function brief(Call $call): array
    {
        return array_map(static fn (array $s): array => ['section' => $s['section']->value, 'text' => $s['text'], 'uncovered' => $s['found']], $call->briefSheet());
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(Scenario $scenario, string $mode): array
    {
        if ('chat' === $mode) {
            return $this->start($scenario);
        }

        return [
            'scenario' => $scenario->publicView(),
            'link' => $this->links->briefing($this->me->member(), $scenario),
            'note' => 'Give the seller the link: it opens their briefing and the phone screen, signed in, to take the call out loud (valid 15 minutes). Afterwards, my_progress and the debrief app show how it went.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function start(Scenario $scenario): array
    {
        try {
            $call = $this->calls->start($this->me->member(), $scenario);
        } catch (CallException $e) {
            throw new ToolCallException($this->translator->trans($e->getMessage(), domain: 'messages', locale: 'en'));
        }

        return [
            'call' => $call->id,
            'scenario' => $scenario->publicView(),
            'persona_says' => $scenario->opening,
            'note' => 'Show the seller the briefing (situation, document as is, objectives, who is on the line) and the persona\'s line, then relay their words, as said, with reply.',
        ];
    }

    private function scenario(int $id): Scenario
    {
        $scenario = $this->scenarios->find($id);
        if (!$scenario instanceof Scenario || !$scenario->active || !$scenario->visibleTo($this->me->member())) {
            throw new ToolCallException(\sprintf('No scenario #%d: library lists them.', $id));
        }

        return $scenario;
    }
}
