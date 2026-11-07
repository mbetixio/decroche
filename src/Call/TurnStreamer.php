<?php

declare(strict_types=1);

namespace App\Call;

use App\Entity\Call;
use App\Repository\CompanyRepository;
use App\Voice\Prosody;
use App\Voice\SpeechSynthesizer;
use App\Voice\Synthesis;
use App\Voice\VoiceCasting;

/**
 * One exchange of a call on the phone screen, as a stream of events: each sentence of the persona's reply leaves as
 * soon as its voice is ready (synthesized while the model writes the next one), in order, then the turn's outcome.
 *
 * Events: {t: "say", text, audio: base64 MP3 or null}, then {t: "done", ...Turn} or {t: "error", error: key}.
 */
final readonly class TurnStreamer
{
    public function __construct(
        private CallManager $calls,
        private SpeechSynthesizer $synthesizer,
        private VoiceCasting $casting,
        private CompanyRepository $companies,
    ) {
    }

    /**
     * Starts the exchange: the seller's line is checked and recorded, and the model asked, before anything is sent.
     *
     * @param bool $voiced whether to synthesize the persona's voice (otherwise the browser speaks)
     *
     * @return \Generator<int, array<string, mixed>>
     *
     * @throws CallException when the line is refused (see {@see CallManager::talk()})
     */
    public function stream(Call $call, string $text, ?int $voiceMs, bool $interrupted, bool $voiced): \Generator
    {
        $turn = $this->calls->talk($call, $text, $voiceMs, $interrupted);
        $turn->current();

        return $this->events($turn, $voiced && $this->synthesizer->isAvailable() ? [$this->casting->voice($call->scenario, $this->companies->get()->language), $this->casting->prosody($call->scenario)] : null);
    }

    /**
     * @param \Generator<int, string, mixed, Turn> $turn
     * @param array{0: string, 1: Prosody}|null    $voice
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function events(\Generator $turn, ?array $voice): \Generator
    {
        $splitter = new SentenceSplitter();
        /** @var list<array{0: string, 1: ?Synthesis}> $queue */
        $queue = [];
        $push = function (string $sentence) use (&$queue, $voice): void {
            $queue[] = [$sentence, null !== $voice ? $this->synthesizer->start($sentence, $voice[0], $voice[1]) : null];
        };
        // The sentences leave in order, each when its voice is ready (or at once, without one).
        $drain = static function (bool $wait) use (&$queue): \Generator {
            while ([] !== $queue) {
                [$text, $synthesis] = $queue[0];
                if (null !== $synthesis && !$synthesis->isDone()) {
                    if (!$wait) {
                        return;
                    }
                    $synthesis->wait();
                }
                array_shift($queue);
                $audio = $synthesis?->audio();
                yield ['t' => 'say', 'text' => $text, 'audio' => null !== $audio ? base64_encode($audio) : null];
            }
        };

        try {
            for (; $turn->valid(); $turn->next()) {
                array_map($push, $splitter->feed($turn->current()));
                yield from $drain(false);
            }
            if (null !== $rest = $splitter->finish()) {
                $push($rest);
            }
            yield from $drain(true);
            yield ['t' => 'done'] + $turn->getReturn()->toArray();
        } catch (\Throwable) {
            yield from $drain(true);
            yield ['t' => 'error', 'error' => 'call.error.line_cut'];
        }
    }
}
