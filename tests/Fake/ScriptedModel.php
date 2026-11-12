<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The language model in tests: a Symfony AI mock platform that answers each kind of prompt (scenario writer, coach,
 * offer analyst, persona) with a fixed, valid answer, so that every flow runs without the network. The persona's
 * answer comes as a stream, as from a real provider. A test can also make it fail: unreachable, garbled for the next
 * answers, or answering a given kind of prompt its own way.
 */
final class ScriptedModel
{
    /** Every prompt received, as JSON, for assertions. */
    public static array $prompts = [];

    /** The provider cannot be reached: every call fails as on a network error. */
    public static bool $unreachable = false;

    /** The connection drops in the middle of the next streamed answer. */
    public static bool $cutOff = false;

    /** How many of the next answers come garbled (not the JSON asked for). */
    public static int $garbled = 0;

    /** @var array<string, string> answers of a test's own, by a phrase of the prompt they answer */
    public static array $answers = [];

    /**
     * Back to the well-behaved model, before each test.
     */
    public static function reset(): void
    {
        self::$prompts = [];
        self::$unreachable = false;
        self::$cutOff = false;
        self::$garbled = 0;
        self::$answers = [];
    }

    public static function platform(?EventDispatcherInterface $dispatcher = null): Platform
    {
        return MockPlatformFactory::createPlatform(eventDispatcher: $dispatcher, responses: static function ($model, array|string $payload, array $options = []): TextResult|StreamResult|ToolCallResult {
            $prompt = \is_string($payload) ? $payload : (string) json_encode($payload, \JSON_UNESCAPED_UNICODE);
            self::$prompts[] = $prompt;
            if (self::$unreachable) {
                throw new \RuntimeException('Connection refused.');
            }
            $stream = true === ($options['stream'] ?? false);
            $text = static fn (string $answer): TextResult|StreamResult => $stream ? new StreamResult(self::stream($answer)) : new TextResult($answer);
            if (self::$garbled > 0) {
                --self::$garbled;

                return $text('Sorry, I cannot help with that.');
            }
            foreach (self::$answers as $phrase => $answer) {
                if (str_contains($prompt, $phrase)) {
                    return $text($answer);
                }
            }
            if (isset($options['tools']) && null !== $agent = self::agent($prompt, \is_array($payload) ? $payload : [], $stream)) {
                return $agent;
            }

            return $text(self::answer($prompt));
        });
    }

    /**
     * An agent's round, with its tools: the writer and the coach investigate then give their notes; the persona
     * looks things up when the seller's line calls for it (a day, a meeting to book, a ticket, an earlier call), saying
     * a few words first, then answers once the tool has answered. Null when no tool is called for.
     *
     * @param array<string, mixed> $payload
     */
    private static function agent(string $prompt, array $payload, bool $stream): TextResult|StreamResult|ToolCallResult|null
    {
        $messages = \is_array($payload['messages'] ?? null) ? array_values($payload['messages']) : [];
        $last = end($messages);
        $answered = \is_array($last) && 'tool' === ($last['role'] ?? null);
        $call = static fn (string $name, array $arguments = []): ToolCall => new ToolCall('call_'.$name.'_'.\count($messages), $name, $arguments);

        if (str_contains($prompt, 'You write phone call situations') || str_contains($prompt, 'You write role-play scenarios')) {
            return $answered
                ? new TextResult('Notes: the seller recently met nobody at Freshcart; the offer has dedicated teams.')
                : new ToolCallResult([$call('recent_calls', ['limit' => 5]), $call('read_offer', ['topic' => 'teams'])]);
        }
        if (str_contains($prompt, 'experienced sales coach')) {
            return $answered
                ? new TextResult('Notes: the seller asked "What budget have you set aside?"; no meeting booked.')
                : new ToolCallResult([$call('previous_debriefs', ['limit' => 3]), $call('find_in_transcript', ['words' => 'budget']), $call('client_actions', ['meetingsOnly' => false]), $call('read_offer', ['topic' => ''])]);
        }
        if (!str_contains($prompt, 'You are playing a part in a sales training')) {
            return null;
        }
        if ($answered) {
            $said = mb_strtolower(\is_string($last['content'] ?? null) ? $last['content'] : (json_encode($last['content'] ?? '', \JSON_UNESCAPED_UNICODE) ?: ''));
            $reply = match (true) {
                str_contains($said, '"booked":true') => 'Done, it is in my calendar. See you then.',
                str_contains($said, '"booked":false') => 'I am busy then, I am afraid. Another time?',
                str_contains($said, 'free_hours') => 'I am free in the morning, except at ten.',
                str_contains($said, 'never spoken') => 'No, I do not think we have spoken before.',
                default => 'Yes, I have it here: thirty-seven orders lost this month.',
            };

            return $stream ? new StreamResult(self::stream($reply)) : new TextResult($reply);
        }
        $seller = mb_strtolower((string) (\is_array($last) ? json_encode($last['content'] ?? '', \JSON_UNESCAPED_UNICODE) : ''));
        $tuesday = new \DateTimeImmutable('next tuesday');
        $tool = match (true) {
            str_contains($seller, 'book') => $call('book_meeting', ['date' => $tuesday->format('Y-m-d'), 'time' => str_contains($seller, 'ten') ? '10:00' : '11:00', 'minutes' => 30, 'subject' => 'Workshop']),
            str_contains($seller, 'tuesday') => $call('check_calendar', ['date' => $tuesday->format('Y-m-d')]),
            str_contains($seller, 'ticket') => $call('look_up', ['source' => 'tickets', 'query' => 'order lost']),
            str_contains($seller, 'last time') => $call('previous_calls'),
            default => null,
        };
        if (null === $tool) {
            return null;
        }

        return $stream
            ? new StreamResult((static function () use ($tool): \Generator {
                yield new TextDelta('Let me check. ');
                yield new ToolCallComplete([$tool]);
            })())
            : new ToolCallResult([$tool]);
    }

    private static function answer(string $prompt): string
    {
        return match (true) {
            str_contains($prompt, 'You write phone call situations') => (string) json_encode(self::scenario() + ['material' => "The web form:\nName: Ana Diaz\nMessage: we need a quote.", 'objectives' => ['Find the need', 'Explain the pricing', 'Book a meeting']]),
            str_contains($prompt, 'You write role-play scenarios') => (string) json_encode(self::scenario() + ['type' => 'discovery']),
            str_contains($prompt, 'You prepare a role-play to train the sellers') => (string) json_encode(self::workspace()),
            str_contains($prompt, 'experienced sales coach') => (string) json_encode(self::debrief()),
            str_contains($prompt, 'sales training tool') => (string) json_encode(['name' => 'Acme', 'offer' => "Who: Acme builds software for retailers.\n\nThe offers:\n- Dedicated teams\n- Technical audits\n\nThe terms: to be specified.", 'sectors' => ['retail', 'logistics']]),
            str_contains($prompt, 'catalogue of situations') => (string) json_encode(['situations' => array_map(self::situation(...), ['price_request', 'unhappy_client', 'cold_call', 'renewal', 'referral'], ['Price request', 'Unhappy client', 'Cold call', 'Renewal', 'Referral'])]),
            str_contains($prompt, 'You are playing a part in a sales training') => self::persona($prompt),
            default => 'Hm.',
        };
    }

    /**
     * An answer as a provider streams it: a few words at a time.
     *
     * @return \Generator<int, TextDelta>
     */
    private static function stream(string $answer): \Generator
    {
        foreach (str_split($answer, 12) as $i => $piece) {
            if (self::$cutOff && $i > 0) {
                self::$cutOff = false;

                throw new \RuntimeException('Connection reset.');
            }
            yield new TextDelta($piece);
        }
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
            'workspace' => self::workspace(),
        ];
    }

    /**
     * Ana's systems: her calendar (Tuesday 10am taken, the rest of the week mostly free) and her support tickets.
     *
     * @return array<string, mixed>
     */
    public static function workspace(): array
    {
        $tuesday = new \DateTimeImmutable('next tuesday');

        return [
            'calendar' => [
                ['when' => $tuesday->format('Y-m-d').' 10:00', 'minutes' => 60, 'what' => 'Weekly operations review'],
                ['when' => $tuesday->format('Y-m-d').' 14:00', 'minutes' => 90, 'what' => 'Warehouse visit'],
            ],
            'sources' => [
                ['key' => 'tickets', 'label' => 'Support tickets', 'description' => 'Problems reported by the warehouse and the shops.', 'entries' => [
                    ['title' => 'T-1042 Order lost between the shop and the warehouse', 'when' => '2026-11-03', 'details' => [['name' => 'Status', 'value' => 'Open'], ['name' => 'Orders affected', 'value' => '37']], 'note' => 'Third time this month: the ERP and the shop tool disagree on stock.'],
                    ['title' => 'T-1038 Invoice sent twice', 'when' => '2026-10-28', 'details' => [['name' => 'Status', 'value' => 'Closed']], 'note' => ''],
                ]],
            ],
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
