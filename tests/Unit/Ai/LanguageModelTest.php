<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Ai\Output\OfferSheet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The agents behind LanguageModel, on a mock platform: the persona streams across a tool call, the writer and the
 * coach investigate then answer in shape (or answer without notes when the investigation fails), and each model
 * family gets its own reasoning option.
 */
final class LanguageModelTest extends TestCase
{
    /** @var list<array{model: string, payload: array<mixed>|string, options: array<string, mixed>}> */
    private array $calls = [];

    public function testThePersonaStreamsAcrossAToolCall(): void
    {
        $model = $this->model(function (array $options, bool $answered): StreamResult {
            return new StreamResult((static function () use ($answered): \Generator {
                if ($answered) {
                    yield new TextDelta('I am free at nine.');

                    return;
                }
                yield new TextDelta('Let me check.');
                yield new ToolCallComplete([new ToolCall('1', 'calendar', ['day' => 'tuesday'])]);
            })());
        }, persona: 'openai/gpt-oss-120b', personaReasoning: 'none');

        self::assertSame('Let me check. I am free at nine.', implode('', iterator_to_array($model->stream('You play a client.', [['role' => 'user', 'content' => 'Tuesday?']]), false)));
        self::assertSame('openai/gpt-oss-120b', $this->calls[0]['model']);
        // gpt-oss cannot reason less than "low".
        self::assertSame('low', $this->calls[0]['options']['reasoning_effort']);
        self::assertCount(2, $this->calls, 'A second round once the tool answered.');
    }

    public function testAnAgentInvestigatesThenAnswersInShape(): void
    {
        $sheet = ['name' => 'Acme', 'offer' => 'Dedicated teams.', 'sectors' => ['retail']];
        $model = $this->model(static fn (array $options, bool $answered): TextResult|ToolCallResult => match (true) {
            isset($options[PlatformSubscriber::RESPONSE_FORMAT]) => new TextResult((string) json_encode($sheet)),
            $answered => new TextResult('Notes: they sell dedicated teams.'),
            default => new ToolCallResult([new ToolCall('1', 'read_offer', ['topic' => 'teams'])]),
        }, writer: 'openai/gpt-oss-120b', writerReasoning: 'medium');

        $work = $model->work('writer', 'You write offer sheets.', 'The company: Acme', OfferSheet::class);

        self::assertSame($sheet, $work->toArray());
        self::assertCount(3, $this->calls, 'A tool round, the notes, then the answer.');
        $tools = (string) json_encode($this->calls[0]['options']['tools']);
        self::assertStringContainsString('read_offer', $tools, 'The agent offers its own tools.');
        self::assertSame('medium', $this->calls[0]['options']['reasoning_effort']);
        self::assertStringContainsString('Notes: they sell dedicated teams.', (string) json_encode($this->calls[2]['payload']), 'The notes feed the answer.');
        self::assertTrue($this->calls[2]['options']['response_format']['json_schema']['strict']);
    }

    public function testEachToolIsOneStepHoweverOftenItIsCalled(): void
    {
        $rounds = 0;
        $model = $this->model(static function (array $options, bool $answered) use (&$rounds): TextResult|ToolCallResult {
            if (isset($options[PlatformSubscriber::RESPONSE_FORMAT])) {
                return new TextResult((string) json_encode(['name' => 'Acme', 'offer' => 'Teams.', 'sectors' => []]));
            }

            // The offer read three times, over two rounds: one step.
            return ++$rounds <= 2 ? new ToolCallResult([new ToolCall('a'.$rounds, 'read_offer', ['topic' => 'teams']), new ToolCall('b'.$rounds, 'read_offer', ['topic' => 'prices'])]) : new TextResult('Notes.');
        });
        $steps = [];
        $model->work('writer', 'You write offer sheets.', 'The company: Acme', OfferSheet::class, step: static function (string $key) use (&$steps): void {
            $steps[] = $key;
        });

        self::assertSame(['writer.tool.read_offer', 'writer.writing'], $steps);
    }

    public function testWithoutNotesTheAnswerIsWrittenAllTheSame(): void
    {
        $model = $this->model(static function (array $options): TextResult {
            if (!isset($options[PlatformSubscriber::RESPONSE_FORMAT])) {
                throw new \RuntimeException('The tools are down.');
            }

            return new TextResult((string) json_encode(['name' => 'Acme', 'offer' => 'Teams.', 'sectors' => []]));
        }, coach: 'deepseek-ai/DeepSeek-V4.1-Flash', coachReasoning: 'medium');

        self::assertSame('Acme', $model->work('coach', 'You write offer sheets.', 'The company: Acme', OfferSheet::class)->name);
        // DeepSeek keeps thinking at "medium".
        self::assertArrayNotHasKey('thinking', $this->calls[1]['options']);
    }

    public function testDeepSeekStopsThinkingAtLowReasoning(): void
    {
        $model = $this->model(static fn (): StreamResult => new StreamResult((static fn (): \Generator => yield new TextDelta('Hi.'))()), persona: 'deepseek-ai/DeepSeek-V4.1-Flash', personaReasoning: 'low');
        iterator_to_array($model->stream('You play a client.', []), false);

        self::assertSame(['type' => 'disabled'], $this->calls[0]['options']['thinking']);
    }

    public function testOtherModelsGetNoReasoningOption(): void
    {
        $model = $this->model(static fn (): StreamResult => new StreamResult((static fn (): \Generator => yield new TextDelta('Hi.'))()), persona: 'mistral-small-latest', personaReasoning: 'high');
        iterator_to_array($model->stream('You play a client.', []), false);

        self::assertArrayNotHasKey('reasoning_effort', $this->calls[0]['options']);
        self::assertArrayNotHasKey('thinking', $this->calls[0]['options']);
    }

    public function testAnAnswerOutOfShapeIsAModelError(): void
    {
        $this->expectException(ModelException::class);

        $this->model(static fn (): TextResult => new TextResult('Sorry, I cannot help with that.'))->work('coach', 'You write offer sheets.', 'The company: Acme', OfferSheet::class);
    }

    public function testAMissingModelIsSaid(): void
    {
        $this->expectExceptionObject(new ModelException('No model is set: give AI_COACH_MODEL in .env.local.'));

        $this->model(static fn (): TextResult => new TextResult('{}'), coach: '')->structured('You write offer sheets.', 'The company: Acme', OfferSheet::class);
    }

    /**
     * @param \Closure(array<string, mixed>, bool): (TextResult|StreamResult|ToolCallResult) $answer given the request's options and whether a tool just answered
     */
    private function model(\Closure $answer, string $persona = 'persona-model', string $coach = 'coach-model', string $personaReasoning = 'low', string $coachReasoning = 'medium', string $writer = 'writer-model', string $writerReasoning = 'low'): LanguageModel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());
        $platform = MockPlatformFactory::createPlatform(function ($model, array|string $payload, array $options) use ($answer): TextResult|StreamResult|ToolCallResult {
            $this->calls[] = ['model' => $model->getName(), 'payload' => $payload, 'options' => $options];
            $messages = \is_array($payload) ? ($payload['messages'] ?? []) : [];
            $last = \is_array($messages) ? end($messages) : null;

            return $answer($options, \is_array($last) && 'tool' === ($last['role'] ?? null));
        }, eventDispatcher: $dispatcher);
        $toolbox = new Toolbox([new LanguageModelTestTools()], eventDispatcher: $dispatcher);
        $agent = static fn (string $model): Agent => new Agent($platform, $model, toolbox: $toolbox, eventDispatcher: $dispatcher);

        return new LanguageModel($platform, new NullLogger(), $agent($persona), $agent($coach), $agent($writer), $persona, $coach, $writer, $personaReasoning, $coachReasoning, $writerReasoning);
    }
}
