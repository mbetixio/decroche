<?php

namespace App\Ai;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Platform\Bridge\Generic\Factory;
use Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Le modèle qui joue les personas et rédige les débriefs, via Symfony AI : toute API compatible OpenAI, réglée dans
 * l'environnement (AI_*) ou enregistrée en base (la clé scellée par le Vault).
 */
final class Brain
{
    public const PRESETS = [
        'opencode_go' => ['label' => 'OpenCode Go', 'url' => 'https://opencode.ai/zen/go/v1', 'model' => 'deepseek-v4.1-flash', 'headers' => '{"x-opencode-session": "sparring"}'],
        'openai' => ['label' => 'OpenAI', 'url' => 'https://api.openai.com/v1', 'model' => 'gpt-5.4-mini', 'headers' => '{}'],
        'deepseek' => ['label' => 'DeepSeek', 'url' => 'https://api.deepseek.com/v1', 'model' => 'deepseek-chat', 'headers' => '{}'],
        'openrouter' => ['label' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1', 'model' => 'deepseek/deepseek-chat', 'headers' => '{}'],
        'mistral' => ['label' => 'Mistral', 'url' => 'https://api.mistral.ai/v1', 'model' => 'mistral-medium-latest', 'headers' => '{}'],
        'huggingface' => ['label' => 'Hugging Face', 'url' => 'https://router.huggingface.co/v1', 'model' => 'openai/gpt-oss-120b', 'headers' => '{}'],
        'ollama' => ['label' => 'Ollama (this machine)', 'url' => 'http://127.0.0.1:11434/v1', 'model' => 'llama3.1', 'headers' => '{}'],
        'custom' => ['label' => 'Another OpenAI-compatible API', 'url' => '', 'model' => '', 'headers' => '{}'],
    ];

    private ?PlatformInterface $platform = null;
    private ?string $model = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Vault $vault,
        private readonly HttpClientInterface $http,
        private readonly ?EventDispatcherInterface $events = null,
        #[Autowire('%env(AI_BASE_URL)%')]
        private readonly string $envUrl = '',
        #[Autowire('%env(AI_API_KEY)%')]
        private readonly string $envKey = '',
        #[Autowire('%env(AI_MODEL)%')]
        private readonly string $envModel = '',
        #[Autowire('%env(AI_HEADERS)%')]
        private readonly string $envHeaders = '{}',
    ) {
    }

    /**
     * The tests, and anything else that brings its own platform.
     */
    public function use(PlatformInterface $platform, string $model): void
    {
        [$this->platform, $this->model] = [$platform, $model];
    }

    public function ready(): bool
    {
        return null !== $this->platform || '' !== $this->connection()['url'];
    }

    public function label(): string
    {
        $c = $this->connection();

        return null !== $this->platform ? (string) $this->model : ('' === $c['url'] ? 'non connecté' : $c['model'].' · '.(parse_url($c['url'], \PHP_URL_HOST) ?: $c['url']));
    }

    /**
     * Whether a reasoning model thinks before it answers. Thinking is all or nothing (an effort or a budget is ignored)
     * and takes minutes on a long answer: seul ce que personne n'attend réfléchit.
     */
    public const OFF = 'off';
    public const FULL = 'full';

    /**
     * @param list<array{role: string, content: string}> $messages after the system prompt
     * @param self::OFF|self::FULL                      $think    a reasoning model thinks silently first, for minutes on long answers
     */
    public function text(string $system, array $messages, float $temperature = 0.8, string $think = self::OFF): string
    {
        $bag = new MessageBag(Message::forSystem($system), ...array_map(static fn (array $m) => 'assistant' === $m['role'] ? Message::ofAssistant($m['content']) : Message::ofUser($m['content']), $messages));
        [$platform, $model] = $this->platform();

        return trim($platform->invoke($model, $bag, ['temperature' => $temperature] + self::thinking($model, $think))->asText());
    }

    /**
     * La réponse au fil de l'eau, morceau par morceau (une plateforme qui ne sait pas streamer rend tout d'un bloc).
     *
     * @param list<array{role: string, content: string}> $messages after the system prompt
     *
     * @return \Generator<int, string>
     */
    public function stream(string $system, array $messages, float $temperature = 0.8): \Generator
    {
        $bag = new MessageBag(Message::forSystem($system), ...array_map(static fn (array $m) => 'assistant' === $m['role'] ? Message::ofAssistant($m['content']) : Message::ofUser($m['content']), $messages));
        [$platform, $model] = $this->platform();
        $deferred = $platform->invoke($model, $bag, ['temperature' => $temperature, 'stream' => true] + self::thinking($model, self::OFF));
        if (!$deferred->getResult() instanceof \Symfony\AI\Platform\Result\StreamResult) {
            yield $deferred->asText();

            return;
        }
        foreach ($deferred->asStream() as $delta) {
            if ($delta instanceof \Symfony\AI\Platform\Result\Stream\Delta\TextDelta) {
                yield $delta->getText();
            } elseif (\is_string($delta)) {
                yield $delta;
            }
        }
    }

    /**
     * The options that set how much a reasoning model thinks, for the families that understand them.
     *
     * @return array<string, mixed>
     */
    public static function thinking(string $model, string $think): array
    {
        if (self::FULL === $think) {
            return [];
        }

        return match (true) {
            // Sans réflexion, la réponse est coupée à 8k jetons sauf indication contraire.
            str_contains($model, 'deepseek') => ['thinking' => ['type' => 'disabled'], 'max_tokens' => 32000],
            (bool) preg_match('/^(openai\/)?(gpt-5|o\d)/', $model) => ['reasoning_effort' => 'minimal'],
            default => [],
        };
    }

    /**
     * A JSON object from the model (the first {…} of its answer).
     *
     * @param self::OFF|self::FULL $think
     *
     * @return array<string, mixed>
     */
    public function json(string $system, string $user, float $temperature = 0.9, string $think = self::OFF): array
    {
        try {
            $text = $this->text($system, [['role' => 'user', 'content' => $user]], $temperature, $think);
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface|\Symfony\AI\Platform\Exception\RuntimeException) {
            // Une longue réponse qui a expiré : une fois de plus.
            $text = $this->text($system, [['role' => 'user', 'content' => $user]], $temperature, $think);
        }
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false !== $start && false !== $end) {
            $data = json_decode(substr($text, $start, $end - $start + 1), true);
            if (\is_array($data)) {
                return $data;
            }
        }

        throw new \RuntimeException('Le modèle n\'a pas répondu en JSON.');
    }

    /**
     * @return array{url: string, key: string, model: string, headers: string, preset: string}
     */
    public function connection(): array
    {
        $saved = $this->em->find(Setting::class, 'ai');
        if (null !== $saved) {
            $c = json_decode($saved->value, true);
            try {
                $key = '' === ($c['key'] ?? '') ? '' : $this->vault->open($c['key']);
            } catch (\RuntimeException) {
                $key = '';
            }

            return ['url' => (string) $c['url'], 'key' => $key, 'model' => (string) $c['model'], 'headers' => (string) ($c['headers'] ?? '{}'), 'preset' => (string) ($c['preset'] ?? 'custom')];
        }

        return ['url' => $this->envUrl, 'key' => $this->envKey, 'model' => $this->envModel, 'headers' => $this->envHeaders ?: '{}', 'preset' => 'custom'];
    }

    /**
     * Checks the connection with a one-word question, then keeps it.
     */
    public function connect(string $preset, string $url, string $key, string $model, string $headers): string
    {
        $url = rtrim(trim($url), '/');
        if (!preg_match('#^https?://#', $url) || '' === trim($model)) {
            throw new \InvalidArgumentException('Indiquez l\'adresse de l\'API et le modèle.');
        }
        if (!\is_array(json_decode($headers ?: '{}', true))) {
            throw new \InvalidArgumentException('Les en-têtes doivent être un objet JSON, par exemple {"x-session": "sparring"}.');
        }
        if ('' === $key) {
            $key = $this->connection()['url'] === $url ? $this->connection()['key'] : '';
        }
        $platform = $this->build($url, $key, $headers ?: '{}');
        $answer = trim($platform->invoke(trim($model), new MessageBag(Message::ofUser('Réponds en un mot : prêt.')))->asText());
        $setting = $this->em->find(Setting::class, 'ai') ?? new Setting('ai', '');
        $setting->value = json_encode(['preset' => $preset, 'url' => $url, 'key' => '' === $key ? '' : $this->vault->seal($key), 'model' => trim($model), 'headers' => $headers ?: '{}'], \JSON_THROW_ON_ERROR);
        $this->em->persist($setting);
        $this->em->flush();
        $this->platform = null;

        return $answer;
    }

    /**
     * @return array{PlatformInterface, string}
     */
    private function platform(): array
    {
        if (null !== $this->platform) {
            return [$this->platform, (string) $this->model];
        }
        $c = $this->connection();
        if ('' === $c['url']) {
            throw new \RuntimeException('Aucun modèle n\'est connecté : renseignez AI_BASE_URL, AI_API_KEY et AI_MODEL dans .env.local.');
        }

        return [$this->build($c['url'], $c['key'], $c['headers']), $c['model']];
    }

    private function build(string $url, string $key, string $headers): PlatformInterface
    {
        $http = $this->http->withOptions(['headers' => json_decode($headers, true) ?: [], 'timeout' => 600, 'max_duration' => 900]);

        return Factory::createPlatform($url, '' === $key ? null : $key, $http, new FallbackModelCatalog(), Contract::create([new ToolNormalizer()]), $this->events, supportsEmbeddings: false, completionsPath: '/chat/completions');
    }
}
