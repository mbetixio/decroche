<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The language model in tests: a Symfony AI mock platform that answers each kind of prompt (scenario writer, coach,
 * offer analyst, persona) with a fixed, valid answer, so that every flow runs without the network.
 */
final class ScriptedModel
{
    /** Every prompt received, as JSON, for assertions. */
    public static array $prompts = [];

    public static function platform(?EventDispatcherInterface $dispatcher = null): Platform
    {
        return MockPlatformFactory::createPlatform(eventDispatcher: $dispatcher, responses: static function ($model, array|string $payload): TextResult {
            $prompt = \is_string($payload) ? $payload : (string) json_encode($payload, \JSON_UNESCAPED_UNICODE);
            self::$prompts[] = $prompt;

            return new TextResult(match (true) {
                str_contains($prompt, 'You write phone call situations') => (string) json_encode(self::scenario() + ['material' => "The web form:\nName: Ana Diaz\nMessage: we need a quote.", 'objectives' => ['Find the need', 'Explain the pricing', 'Book a meeting']]),
                str_contains($prompt, 'You write role-play scenarios') => (string) json_encode(self::scenario() + ['type' => 'discovery']),
                str_contains($prompt, 'experienced sales coach') => (string) json_encode(self::debrief()),
                str_contains($prompt, 'sales training tool') => (string) json_encode(['name' => 'Acme', 'offer' => "Who: Acme builds software for retailers.\n\nThe offers:\n- Dedicated teams\n- Technical audits\n\nThe terms: to be specified.", 'sectors' => ['retail', 'logistics']]),
                str_contains($prompt, 'catalogue of situations') => (string) json_encode(['situations' => array_map(self::situation(...), ['price_request', 'unhappy_client', 'cold_call', 'renewal', 'referral'], ['Price request', 'Unhappy client', 'Cold call', 'Renewal', 'Referral'])]),
                str_contains($prompt, 'You are playing a part in a sales training') => self::persona($prompt),
                default => 'Hm.',
            });
        });
    }

    /**
     * The persona: gives the budget away when asked about it, leaves when told goodbye, otherwise listens.
     */
    private static function persona(string $prompt): string
    {
        $last = mb_strtolower((string) substr($prompt, (int) strrpos($prompt, '"content"')));

        return match (true) {
            str_contains($last, 'budget') => '[devoiler:budget] We set aside about thirty thousand euros. Why do you ask?',
            str_contains($last, 'goodbye') => 'Fine, talk soon then. Goodbye. [fin]',
            default => 'I see. Go on, I am listening.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function scenario(): array
    {
        return [
            'title' => 'A price request from the website',
            'context' => 'Ana Diaz filled in the web form asking for your rates. You call her back.',
            'goal' => 'Understand the need and book a meeting.',
            'persona' => ['name' => 'Ana Diaz', 'gender' => 'f', 'role' => 'Operations director', 'company' => 'Freshcart, retail, 120 people, Lyon', 'personality' => 'Direct and busy, she likes figures.'],
            'opening' => 'Ana Diaz speaking, hello?',
            'brief' => ['setup' => 'She is between two meetings.', 'budget' => 'About 30k euros.', 'pain' => 'Orders are lost between tools.', 'decision' => 'She decides with her CFO.', 'objections' => 'Offshore worries her.', 'yes' => 'A short workshop with a fixed price.', 'secret' => 'A competitor already quoted.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function situation(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'emoji' => '📞', 'family' => 'prospecting', 'direction' => 'outgoing', 'material' => 'The form', 'pitch' => 'A prospect wants a price before explaining their need, and compares three providers.', 'objectives' => ['Find the need', 'Explain the pricing', 'Book a meeting']];
    }

    /**
     * @return array<string, mixed>
     */
    private static function debrief(): array
    {
        $grade = static fn (string $key, int $score = 7, string $comment = 'Good questions.'): array => ['key' => $key, 'score' => $score, 'comment' => $comment];

        return [
            'criteria' => [$grade('discovery'), $grade('questions'), $grade('listening'), $grade('objections'), $grade('value'), $grade('closing', 4, 'No next step.')],
            'summary' => 'A short but well led call.',
            'nextStep' => false,
            'outcome' => 'The deal is still open.',
            'result' => 'Budget found, no next step',
            'objectives' => [['objective' => 'Find the need', 'status' => 'partial', 'evidence' => 'She mentioned lost orders.']],
            'discovered' => ['budget'],
            'missed' => [['rubric' => 'pain', 'what' => 'Orders are lost', 'how' => 'What happens when an order goes wrong?']],
            'best' => [['quote' => 'What budget have you set aside?', 'why' => 'Direct and useful.', 'better' => '']],
            'worst' => [['quote' => 'Hello, it is about your request.', 'why' => 'Too vague.', 'better' => 'Hello Ana, you asked for our rates: may I ask what for?']],
            'speech' => '',
            'tips' => ['Ask about the pain first.', 'Rephrase.', 'Propose a next step.'],
        ];
    }
}
