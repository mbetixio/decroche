<?php

declare(strict_types=1);

namespace App\Call;

use App\Call\Coach\Coach;
use App\Call\Persona\Persona;
use App\Call\Persona\PersonaPrompt;
use App\Call\Persona\Reply;
use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\CallEnd;
use App\Enum\Speaker;
use App\Repository\CallRepository;
use Psr\Clock\ClockInterface;

/**
 * The life of a practice call, for the website and the MCP server alike: start, talk, the clock, hang up and debrief,
 * abandon, replay a moment.
 */
final readonly class CallManager
{
    public function __construct(
        private CallRepository $calls,
        private Persona $persona,
        private Coach $coach,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Starts a call; one the member was still in is abandoned.
     *
     * @throws CallException when the scenario is archived
     */
    public function start(Member $member, Scenario $scenario): Call
    {
        if (!$scenario->active) {
            throw new CallException('call.error.archived');
        }
        $this->abandonCurrent($member);
        $call = new Call($member, $scenario, $this->clock->now());
        $this->calls->save($call);

        return $call;
    }

    /**
     * The seller says something; the persona answers as a stream.
     *
     * @param int|null $voiceMs     spoken into the microphone: how long it took, in milliseconds
     * @param bool     $interrupted the seller cut the persona's previous line short
     *
     * @return \Generator<int, string, mixed, Turn> the pieces of the persona's reply; the turn's outcome when done
     *
     * @throws CallException when the line is empty or too long, or the call is over
     */
    public function talk(Call $call, string $text, ?int $voiceMs = null, bool $interrupted = false): \Generator
    {
        $text = trim($text);
        if ('' === $text) {
            throw new CallException('call.error.empty');
        }
        if (mb_strlen($text) > 2000) {
            throw new CallException('call.error.too_long');
        }
        $this->expire($call);
        if (!$call->isLive()) {
            throw new CallException('call.error.over');
        }
        $call->say(Speaker::Seller, $text, $this->clock->now(), $voiceMs);
        $this->calls->save($call);

        /** @var Reply $reply */
        $reply = yield from $this->persona->reply($call, $this->clock->now(), $interrupted ? PersonaPrompt::INTERRUPTED : null);
        $call->say(Speaker::Persona, $reply->text, $this->clock->now());
        foreach ($reply->revealed as $section) {
            $call->reveal($section);
        }
        if ($reply->hungUp) {
            $call->stop(CallEnd::Persona, $this->clock->now());
        } elseif ($call->sellerTurns() >= Call::MAX_TURNS) {
            $call->stop(CallEnd::Clock, $this->clock->now());
        } else {
            $this->expire($call);
        }
        $this->calls->save($call);

        return new Turn($reply->text, !$call->isLive(), $call->endedBy, $call->secondsLeft($this->clock->now()), $call->revealedCount());
    }

    /**
     * The same exchange, waiting for the whole reply.
     *
     * @throws CallException
     */
    public function say(Call $call, string $text, ?int $voiceMs = null): Turn
    {
        $turn = $this->talk($call, $text, $voiceMs);
        foreach ($turn as $piece) {
            // The pieces are not needed here: only the whole turn.
        }

        return $turn->getReturn();
    }

    /**
     * The clock: a live call whose time is up stops (its debrief is still to be written). Returns whether it did.
     */
    public function expire(Call $call): bool
    {
        if ($call->isLive() && 0 === $call->secondsLeft($this->clock->now())) {
            $call->stop(CallEnd::Clock, $this->clock->now());
            $this->calls->save($call);

            return true;
        }

        return false;
    }

    /**
     * Hangs up (if still live) and has the coach debrief the call.
     *
     * @param \Closure|null $step told each step of the debrief
     */
    public function finish(Call $call, ?\Closure $step = null): Call
    {
        $this->expire($call);
        if ($call->isLive()) {
            $call->stop(CallEnd::Seller, $this->clock->now());
            // Hung up for good, even if the coach fails now: the debrief is written again on the next visit.
            $this->calls->save($call);
        }
        if (!$call->isDone()) {
            $result = $this->coach->debrief($call, $this->clock->now(), $step);
            $call->close($result['debrief']->toArray(), $result['score']);
            $this->calls->save($call);
        }

        return $call;
    }

    /**
     * Abandons a live call: it will never be graded.
     *
     * @throws CallException when the call is no longer live
     */
    public function abandon(Call $call): Call
    {
        if (!$call->isLive()) {
            throw new CallException('call.error.not_live');
        }
        $call->stop(CallEnd::Seller, $this->clock->now(), abandoned: true);
        $this->calls->save($call);

        return $call;
    }

    /**
     * Replays a moment: a new call resumed just before the seller's n-th line.
     *
     * @throws CallException when the call is still live
     */
    public function replay(Call $call, int $line): Call
    {
        if ($call->isLive()) {
            throw new CallException('call.error.finish_first');
        }
        $this->abandonCurrent($call->member);
        try {
            $replay = Call::replay($call, $line, $this->clock->now());
        } catch (\InvalidArgumentException $e) {
            throw new CallException($e->getMessage(), previous: $e);
        }
        $this->calls->save($replay);

        return $replay;
    }

    /**
     * The call the member is in or that waits for its debrief, once the clock has been checked.
     */
    public function current(Member $member): ?Call
    {
        $call = $this->calls->current($member);
        if (null !== $call) {
            $this->expire($call);
        }

        return $call;
    }

    private function abandonCurrent(Member $member): void
    {
        $current = $this->calls->current($member);
        if (null !== $current && $current->isLive()) {
            $this->abandon($current);
        }
    }
}
