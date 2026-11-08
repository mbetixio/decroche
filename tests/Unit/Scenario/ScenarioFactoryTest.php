<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scenario;

use App\Ai\ModelException;
use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Enum\Tone;
use App\Scenario\ScenarioFactory;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Building a scenario from loose data (the model's, a library file's, a form's): complete data, defaults and bounds,
 * and incomplete data refused.
 */
final class ScenarioFactoryTest extends TestCase
{
    use Fixtures;

    /**
     * @return array<string, mixed>
     */
    private static function data(): array
    {
        return [
            'title' => ' Unhappy client ',
            'type' => 'unhappy_client',
            'difficulty' => 3,
            'minutes' => 15,
            'context' => 'The project is late.',
            'goal' => 'Keep the client.',
            'persona' => ['name' => 'Claire Dubois', 'role' => 'CTO', 'company' => 'Acme', 'personality' => 'Direct', 'gender' => 'f', 'tone' => 'angry'],
            'opening' => '« Hello, it is Claire. »',
            'brief' => ['pain' => 'Their board is losing patience.', 'objections' => ['Too expensive', 'Too slow'], 'setup' => 'Fuming.', 'other' => 'ignored'],
            'material' => ' The client file ',
            'objectives' => ['Listen', 'Rephrase', 'Agree on a plan'],
        ];
    }

    public function testCompleteData(): void
    {
        $author = self::member();
        $scenario = ScenarioFactory::fromArray(self::data(), $author, self::at());

        self::assertSame('Unhappy client', $scenario->title);
        self::assertSame(MeetingType::Unhappy, $scenario->type);
        self::assertSame(Difficulty::Tough, $scenario->difficulty);
        self::assertSame(15, $scenario->minutes);
        self::assertSame('The project is late.', $scenario->context);
        self::assertSame('Keep the client.', $scenario->goal);
        self::assertSame('Claire Dubois', $scenario->persona()->name);
        self::assertSame(Tone::Angry, $scenario->persona()->tone);
        self::assertSame('Hello, it is Claire.', $scenario->opening);
        self::assertSame('Their board is losing patience.', $scenario->brief(BriefSection::Pain));
        self::assertSame('Too expensive ; Too slow', $scenario->brief(BriefSection::Objections));
        self::assertSame('Fuming.', $scenario->setup());
        self::assertArrayNotHasKey('other', $scenario->briefArray());
        self::assertArrayNotHasKey('budget', $scenario->briefArray());
        self::assertSame('The client file', $scenario->material);
        self::assertSame(['Listen', 'Rephrase', 'Agree on a plan'], $scenario->objectives());
        self::assertSame($author, $scenario->author);
        self::assertEquals(self::at(), $scenario->createdAt);
    }

    /**
     * Quotes are stripped character by character: a last letter sharing a byte with « » “ ” (ë, û…) stays whole.
     */
    public function testQuotesNeverBreakTheLastLetter(): void
    {
        $data = ['opening' => '“Bonjour, c’est Noë”', 'objectives' => ['• Rassurer Noë', '- Proposer un rendez-vous dû']] + self::data();
        $scenario = ScenarioFactory::fromArray($data, null, self::at());

        self::assertSame('Bonjour, c’est Noë', $scenario->opening);
        self::assertTrue(mb_check_encoding($scenario->opening, 'UTF-8'));
        self::assertSame(['Rassurer Noë', 'Proposer un rendez-vous dû'], $scenario->objectives());
    }

    public function testDefaultsAndBounds(): void
    {
        $data = ['title' => 'T', 'persona' => ['name' => 'Paul'], 'opening' => 'Hi', 'brief' => ['pain' => 'P'], 'category' => 'negotiation', 'difficulty' => 9, 'minutes' => 500];
        $scenario = ScenarioFactory::fromArray($data, null, self::at());

        self::assertSame(MeetingType::Negotiation, $scenario->type);
        self::assertSame(Difficulty::Demanding, $scenario->difficulty);
        self::assertSame(60, $scenario->minutes);
        self::assertNull($scenario->material);
        self::assertNull($scenario->author);
        self::assertSame([], $scenario->objectives());

        $short = ScenarioFactory::fromArray(['minutes' => 1, 'type' => 'bogus'] + $data, null, self::at());
        self::assertSame(3, $short->minutes);
        self::assertSame(MeetingType::Discovery, $short->type);

        unset($data['minutes'], $data['difficulty']);
        $default = ScenarioFactory::fromArray($data, null, self::at());
        self::assertSame(12, $default->minutes);
        self::assertSame(Difficulty::Demanding, $default->difficulty);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function incomplete(): iterable
    {
        $data = self::data();

        yield 'no title' => [['title' => '  '] + $data];
        yield 'no persona name' => [['persona' => ['role' => 'CTO']] + $data];
        yield 'persona not a list' => [['persona' => 'Claire'] + $data];
        yield 'no opening' => [['opening' => ' « » '] + $data];
        yield 'no pain' => [['brief' => ['budget' => '50k']] + $data];
        yield 'brief not a list' => [['brief' => 'secret'] + $data];
        yield 'nothing' => [[]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('incomplete')]
    public function testIncompleteData(array $data): void
    {
        $this->expectException(ModelException::class);

        ScenarioFactory::fromArray($data, null, self::at());
    }
}
