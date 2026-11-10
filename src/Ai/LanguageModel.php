<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Output\Output;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The language models behind Décroche, through Symfony AI. Two jobs, two models (the same one twice is fine):
 * - the persona speaks on the phone: its answer is streamed, so a fast model keeps the call lively (AI_PERSONA_MODEL);
 * - the coach and the writers (debriefs, scenarios, offer sheet) answer in a fixed shape, a structured output, so a
 *   model that reads closely and follows a JSON schema fits them (AI_COACH_MODEL).
 * Each job has its own reasoning effort (none, low, medium or high): little on the phone, where every second of silence
 * shows, more for the coach and the writers, who must read the whole call or offer closely (AI_*_REASONING).
 */
final readonly class LanguageModel
{
    public function __construct(
        #[Autowire(service: 'ai.platform.generic.decroche')]
        private PlatformInterface $platform,
        #[Autowire(env: 'AI_PERSONA_MODEL')]
        private string $personaModel,
        #[Autowire(env: 'AI_COACH_MODEL')]
        private string $coachModel,
        #[Autowire(env: 'AI_PERSONA_REASONING')]
        private string $personaReasoning,
        #[Autowire(env: 'AI_COACH_REASONING')]
        private string $coachReasoning,
    ) {
    }

    /**
     * The persona's answer as it is written, piece by piece (a platform that cannot stream gives it in one piece).
     *
     * @param list<array{role: 'user'|'assistant', content: string}> $messages the conversation after the system prompt
     *
     * @return \Generator<int, string>
     */
    public function stream(string $system, array $messages, float $temperature = 0.8): \Generator
    {
        $model = self::name($this->personaModel, 'AI_PERSONA_MODEL');
        $result = $this->platform->invoke($model, self::bag($system, $messages), ['stream' => true] + self::options($model, $temperature, $this->personaReasoning));
        if (!$result->getResult() instanceof StreamResult) {
            yield $result->asText();

            return;
        }
        foreach ($result->asStream() as $delta) {
            if ($delta instanceof TextDelta) {
                yield $delta->getText();
            } elseif (\is_string($delta)) {
                yield $delta;
            }
        }
    }

    /**
     * An answer in the shape of the given class: Symfony AI sends the class as a strict JSON schema and turns the
     * model's JSON back into an object of that class.
     *
     * @template T of Output
     *
     * @param class-string<T> $shape
     *
     * @return T
     *
     * @throws ModelException when the model fails or its answer does not fit the shape
     */
    public function structured(string $system, string $user, string $shape, float $temperature = 0.7): Output
    {
        $model = self::name($this->coachModel, 'AI_COACH_MODEL');
        try {
            $answer = $this->platform->invoke(
                $model,
                self::bag($system, [['role' => 'user', 'content' => $user]]),
                [PlatformSubscriber::RESPONSE_FORMAT => $shape] + self::options($model, $temperature, $this->coachReasoning),
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
            // gpt-oss always reasons a little: "low" is its least.
            str_contains($model, 'gpt-oss') => ['reasoning_effort' => 'none' === $reasoning ? 'low' : $reasoning],
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
