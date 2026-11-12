<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Output\Output;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Décroche's three Symfony AI agents (config/packages/ai.yaml), each with its tools:
 * - the persona speaks on the phone, streamed, and looks things up in their own files, calendar and memory;
 * - the coach investigates the call (earlier debriefs, exact lines, what the client did, the offer), then grades it;
 * - the writer checks the seller's latest calls and the offer, then writes the scenario.
 * The coach and the writer investigate first, then answer in a fixed shape (see work()). Each job has its own model
 * and reasoning effort (AI_*_MODEL, AI_*_REASONING).
 */
final readonly class LanguageModel
{
    public function __construct(
        #[Autowire(service: 'ai.platform.generic.decroche')]
        private PlatformInterface $platform,
        #[Autowire(service: 'ai.agent.persona')]
        private AgentInterface $persona,
        #[Autowire(service: 'ai.agent.coach')]
        private AgentInterface $coach,
        #[Autowire(service: 'ai.agent.writer')]
        private AgentInterface $writer,
        #[Autowire(env: 'AI_PERSONA_MODEL')]
        private string $personaModel,
        #[Autowire(env: 'AI_COACH_MODEL')]
        private string $coachModel,
        #[Autowire(env: 'AI_WRITER_MODEL')]
        private string $writerModel,
        #[Autowire(env: 'AI_PERSONA_REASONING')]
        private string $personaReasoning,
        #[Autowire(env: 'AI_COACH_REASONING')]
        private string $coachReasoning,
        #[Autowire(env: 'AI_WRITER_REASONING')]
        private string $writerReasoning,
    ) {
    }

    /**
     * The persona's answer as it is written, piece by piece, across the rounds of their tool calls (a line said
     * while they look something up, then the rest).
     *
     * @param list<array{role: 'user'|'assistant', content: string}> $messages the conversation after the system prompt
     *
     * @return \Generator<int, string>
     */
    public function stream(string $system, array $messages, float $temperature = 0.8): \Generator
    {
        $model = self::name($this->personaModel, 'AI_PERSONA_MODEL');
        $execution = $this->persona->call(self::bag($system, $messages), ['stream' => true] + self::options($model, $temperature, $this->personaReasoning));
        $rounds = 0;
        foreach ($execution as $update) {
            if (!$update instanceof Progress) {
                continue;
            }
            if (Progress::STAGE_MODEL_REQUEST === $update->getStage() && ++$rounds > 1) {
                // A new round after a tool call: its words follow the previous ones.
                yield ' ';
            } elseif (Progress::STAGE_DELTA === $update->getStage() && $update->getPayload() instanceof TextDelta) {
                yield $update->getPayload()->getText();
            }
        }
    }

    /**
     * Work in the shape of the given class, done by an agent in two steps: it investigates with its tools ("coach":
     * earlier debriefs, the exact lines, what the client did, the offer; "writer": the seller's latest calls, the
     * offer) and writes down its notes; then the notes feed a strict structured answer. Providers refuse, or ignore,
     * tools next to a JSON schema: the two steps keep each one reliable. Without notes (the investigation failed), the
     * answer is written from the prompt alone.
     *
     * @template T of Output
     *
     * @param 'coach'|'writer' $agent
     * @param class-string<T>  $shape
     *
     * @return T
     *
     * @throws ModelException when the answer does not fit the shape
     */
    public function work(string $agent, string $system, string $user, string $shape, float $temperature = 0.7): Output
    {
        [$worker, $model, $reasoning, $tools] = 'coach' === $agent
            ? [$this->coach, self::name($this->coachModel, 'AI_COACH_MODEL'), $this->coachReasoning, ['previous_debriefs', 'find_in_transcript', 'client_actions', 'read_offer']]
            : [$this->writer, self::name($this->writerModel, 'AI_WRITER_MODEL'), $this->writerReasoning, ['recent_calls', 'read_offer']];
        $notes = '';
        try {
            $found = $worker->call(
                self::bag($system."\n\nFirst, investigate with your tools. Then answer with your findings only, as short notes: what you will use, quoted exactly where it matters. Do not write the final answer yet.", [['role' => 'user', 'content' => $user]]),
                ['tools' => $tools] + self::options($model, $temperature, $reasoning),
            )->getContent();
            $notes = \is_string($found) ? trim($found) : '';
        } catch (\Throwable) {
            // The investigation failed (a tool error, too many tool calls…): the answer is written without notes.
        }

        return $this->answer($model, $system, $user.('' !== $notes ? "\n\nYour notes from your investigation:\n".$notes : ''), $shape, $temperature, $reasoning);
    }

    /**
     * An answer in the shape of the given class, without tools: Symfony AI sends the class as a strict JSON schema
     * and turns the model's JSON back into an object of that class.
     *
     * @template T of Output
     *
     * @param class-string<T>  $shape
     * @param 'coach'|'writer' $job   whose model and reasoning effort answer
     *
     * @return T
     *
     * @throws ModelException when the model fails or its answer does not fit the shape
     */
    public function structured(string $system, string $user, string $shape, float $temperature = 0.7, string $job = 'coach'): Output
    {
        return 'writer' === $job
            ? $this->answer(self::name($this->writerModel, 'AI_WRITER_MODEL'), $system, $user, $shape, $temperature, $this->writerReasoning)
            : $this->answer(self::name($this->coachModel, 'AI_COACH_MODEL'), $system, $user, $shape, $temperature, $this->coachReasoning);
    }

    /**
     * @template T of Output
     *
     * @param non-empty-string $model
     * @param class-string<T>  $shape
     *
     * @return T
     *
     * @throws ModelException
     */
    private function answer(string $model, string $system, string $user, string $shape, float $temperature, string $reasoning): Output
    {
        try {
            $answer = $this->platform->invoke(
                $model,
                self::bag($system, [['role' => 'user', 'content' => $user]]),
                [PlatformSubscriber::RESPONSE_FORMAT => $shape] + self::options($model, $temperature, $reasoning),
            )->asObject();
        } catch (ExceptionInterface $e) {
            throw new ModelException('The model did not answer in the expected shape: '.$e->getMessage(), previous: $e);
        }

        return $answer instanceof $shape ? $answer : throw new ModelException(\sprintf('The model did not answer with a "%s".', $shape));
    }

    /**
     * A model's name, which must be set.
     *
     * @return non-empty-string
     *
     * @throws ModelException when the variable is empty
     */
    private static function name(string $model, string $variable): string
    {
        return '' !== $model ? $model : throw new ModelException("No model is set: give {$variable} in .env.local.");
    }

    /**
     * The request's options. The reasoning effort is said the way each model family understands it, and left out for
     * the others, which would reject it.
     *
     * @return array<string, mixed>
     */
    private static function options(string $model, float $temperature, string $reasoning): array
    {
        $model = strtolower($model);
        $reasoning = \in_array($reasoning, ['none', 'low', 'medium', 'high'], true) ? $reasoning : 'low';

        return ['temperature' => $temperature] + match (true) {
            // DeepSeek thinks or not, and cuts long answers short by default.
            str_contains($model, 'deepseek') => ['max_tokens' => 32000] + (\in_array($reasoning, ['none', 'low'], true) ? ['thinking' => ['type' => 'disabled']] : []),
            // gpt-oss always reasons a little ("low" is its least), and its reasoning counts in the output budget.
            str_contains($model, 'gpt-oss') => ['reasoning_effort' => 'none' === $reasoning ? 'low' : $reasoning, 'max_tokens' => 32000],
            str_starts_with($model, 'gpt-5') || str_starts_with($model, 'openai/gpt-5') => ['reasoning_effort' => 'none' === $reasoning ? 'minimal' : $reasoning],
            default => [],
        };
    }

    /**
     * @param list<array{role: 'user'|'assistant', content: string}> $messages
     */
    private static function bag(string $system, array $messages): MessageBag
    {
        return new MessageBag(
            Message::forSystem($system),
            ...array_map(static fn (array $m) => 'assistant' === $m['role'] ? Message::ofAssistant($m['content']) : Message::ofUser($m['content']), $messages),
        );
    }
}
