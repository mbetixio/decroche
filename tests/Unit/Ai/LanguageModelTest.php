<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Ai\Output\OfferSheet;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class LanguageModelTest extends TestCase
{
    /** @var list<array{model: string, options: array<string, mixed>}> */
    private array $calls = [];

    public function testThePersonaStreamsWithItsOwnModelAndLittleReasoning(): void
    {
        $model = $this->model('Hello there.', persona: 'openai/gpt-oss-120b', personaReasoning: 'none');

        self::assertSame('Hello there.', implode('', iterator_to_array($model->stream('You play a client.', [['role' => 'user', 'content' => 'Hi']]), false)));
        self::assertSame('openai/gpt-oss-120b', $this->calls[0]['model']);
        // gpt-oss cannot reason less than "low".
        self::assertSame('low', $this->calls[0]['options']['reasoning_effort']);
    }

    public function testTheCoachAnswersInTheShapeOfTheClass(): void
    {
        $model = $this->model((string) json_encode(['name' => 'Acme', 'offer' => 'Dedicated teams.', 'sectors' => ['retail']]), coach: 'deepseek-ai/DeepSeek-V4.1-Flash', coachReasoning: 'medium');

        $sheet = $model->structured('You write offer sheets.', 'The company: Acme', OfferSheet::class);

        self::assertSame(['name' => 'Acme', 'offer' => 'Dedicated teams.', 'sectors' => ['retail']], $sheet->toArray());
        self::assertSame('deepseek-ai/DeepSeek-V4.1-Flash', $this->calls[0]['model']);
        // The class went out as a strict JSON schema; DeepSeek keeps thinking at "medium".
        self::assertSame('json_schema', $this->calls[0]['options']['response_format']['type']);
        self::assertTrue($this->calls[0]['options']['response_format']['json_schema']['strict']);
        self::assertArrayNotHasKey('thinking', $this->calls[0]['options']);
    }

    public function testDeepSeekStopsThinkingAtLowReasoning(): void
    {
        $model = $this->model('Hi.', persona: 'deepseek-ai/DeepSeek-V4.1-Flash', personaReasoning: 'low');
        iterator_to_array($model->stream('You play a client.', []), false);

        self::assertSame(['type' => 'disabled'], $this->calls[0]['options']['thinking']);
    }

    public function testOtherModelsGetNoReasoningOption(): void
    {
        $model = $this->model('Hi.', persona: 'mistral-small-latest', personaReasoning: 'high');
        iterator_to_array($model->stream('You play a client.', []), false);

        self::assertArrayNotHasKey('reasoning_effort', $this->calls[0]['options']);
        self::assertArrayNotHasKey('thinking', $this->calls[0]['options']);
    }

    public function testAnAnswerOutOfShapeIsAModelError(): void
    {
        $this->expectException(ModelException::class);

        $this->model('Sorry, I cannot help with that.')->structured('You write offer sheets.', 'The company: Acme', OfferSheet::class);
    }

    public function testAMissingModelIsSaid(): void
    {
        $this->expectExceptionObject(new ModelException('No model is set: give AI_COACH_MODEL in .env.local.'));

        $this->model('{}', coach: '')->structured('You write offer sheets.', 'The company: Acme', OfferSheet::class);
    }

    private function model(string $answer, string $persona = 'persona-model', string $coach = 'coach-model', string $personaReasoning = 'low', string $coachReasoning = 'medium'): LanguageModel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());
        $platform = MockPlatformFactory::createPlatform(function ($model, array|string $payload, array $options) use ($answer): TextResult {
            $this->calls[] = ['model' => $model->getName(), 'options' => $options];

            return new TextResult($answer);
        }, eventDispatcher: $dispatcher);

        return new LanguageModel($platform, $persona, $coach, $personaReasoning, $coachReasoning);
    }
}
