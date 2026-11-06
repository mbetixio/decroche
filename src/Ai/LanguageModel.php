<?php

declare(strict_types=1);

namespace App\Ai;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The language model behind the personas, the coach and the writers, through Symfony AI: plain text, JSON, or a
 * stream of text. Reasoning ("thinking") is turned off: nobody waits minutes for a phone reply.
 */
final readonly class LanguageModel
{
    public function __construct(
        #[Autowire(service: 'ai.platform.generic.sparring')]
        private PlatformInterface $platform,
        #[Autowire(env: 'AI_MODEL')]
        private string $model,
    ) {
    }

    /**
     * @param list<array{role: 'user'|'assistant', content: string}> $messages the conversation after the system prompt
     */
    public function text(string $system, array $messages, float $temperature = 0.8): string
    {
        return trim($this->platform->invoke($this->model, self::bag($system, $messages), $this->options($temperature))->asText());
    }

    /**
     * The answer as it is written, piece by piece (a platform that cannot stream gives it in one piece).
     *
     * @param list<array{role: 'user'|'assistant', content: string}> $messages
     *
     * @return \Generator<int, string>
     */
    public function stream(string $system, array $messages, float $temperature = 0.8): \Generator
    {
        $result = $this->platform->invoke($this->model, self::bag($system, $messages), ['stream' => true] + $this->options($temperature));
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
     * A JSON object, read from the answer even when the model wraps it in a code fence or a sentence.
     *
     * @return array<mixed>
     *
     * @throws ModelException when the answer holds no JSON object
     */
    public function json(string $system, string $user, float $temperature = 0.9): array
    {
        $text = $this->text($system, [['role' => 'user', 'content' => $user]], $temperature);
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false !== $start && false !== $end) {
            $data = json_decode(substr($text, $start, $end - $start + 1), true);
            if (\is_array($data)) {
                return $data;
            }
        }

        throw new ModelException('The model did not answer with a JSON object.');
    }

    /**
     * @return array<string, mixed>
     */
    private function options(float $temperature): array
    {
        // DeepSeek models reason before answering unless told not to, and cut long answers short by default.
        $deepseek = str_contains(strtolower($this->model), 'deepseek') ? ['thinking' => ['type' => 'disabled'], 'max_tokens' => 32000] : [];

        return ['temperature' => $temperature] + $deepseek;
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
