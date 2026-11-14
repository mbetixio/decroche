<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Data;

use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Enum\Tone;
use App\Form\Data\ScenarioData;
use App\Scenario\ScenarioFactory;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A library scenario as edited by a manager: read from a scenario, turned into factory data, and applied back
 * (keeping the persona's gender, tone and setup).
 */
final class ScenarioDataTest extends TestCase
{
    use Fixtures;

    public function testOf(): void
    {
        $data = ScenarioData::of(self::scenario(brief: ['pain' => 'Lead dev leaving', 'budget' => '50k', 'setup' => 'On the train']));

        self::assertSame('Discovery with Acme', $data->title);
        self::assertSame(MeetingType::Discovery, $data->type);
        self::assertSame(Difficulty::Demanding, $data->difficulty);
        self::assertSame(10, $data->minutes);
        self::assertSame('Claire Dubois', $data->personaName);
        self::assertSame('CTO', $data->personaRole);
        self::assertSame('Acme', $data->personaCompany);
        self::assertSame('Pragmatic and busy.', $data->personaPersonality);
        self::assertSame('Hello, Claire speaking.', $data->opening);
        self::assertSame(['budget' => '50k', 'pain' => 'Lead dev leaving', 'decision' => '', 'objections' => '', 'yes' => '', 'secret' => ''], $data->brief);
        self::assertArrayNotHasKey('setup', $data->brief);
    }

    public function testToArrayBuildsTheSameScenario(): void
    {
        $scenario = self::scenario();
        $data = ScenarioData::of($scenario)->toArray();

        self::assertSame('discovery', $data['type']);
        self::assertSame(2, $data['difficulty']);
        self::assertSame(['name' => 'Claire Dubois', 'role' => 'CTO', 'company' => 'Acme', 'personality' => 'Pragmatic and busy.'], $data['persona']);

        $rebuilt = ScenarioFactory::fromArray($data, null, self::at());
        self::assertSame($scenario->publicView(), $rebuilt->publicView());
        self::assertSame($scenario->brief(BriefSection::Pain), $rebuilt->brief(BriefSection::Pain));
    }

    public function testApplyTo(): void
    {
        $scenario = self::scenario(brief: ['pain' => 'Old pain', 'budget' => '50k', 'setup' => 'On the train'], persona: self::persona(Tone::Wary, 'f'));
        $data = ScenarioData::of($scenario);
        $data->title = ' New title ';
        $data->type = MeetingType::Closing;
        $data->difficulty = Difficulty::Tough;
        $data->minutes = 20;
        $data->context = ' New context ';
        $data->goal = ' New goal ';
        $data->opening = ' Yes? ';
        $data->personaName = ' Paul Martin ';
        $data->brief['pain'] = ' New pain ';
        $data->brief['budget'] = '  ';
        $data->applyTo($scenario);

        self::assertSame('New title', $scenario->title);
        self::assertSame(MeetingType::Closing, $scenario->type);
        self::assertSame(Difficulty::Tough, $scenario->difficulty);
        self::assertSame(20, $scenario->minutes);
        self::assertSame('New context', $scenario->context);
        self::assertSame('New goal', $scenario->goal);
        self::assertSame('Yes?', $scenario->opening);
        self::assertSame('Paul Martin', $scenario->persona()->name);
        self::assertSame('f', $scenario->persona()->gender);
        self::assertSame(Tone::Wary, $scenario->persona()->tone);
        self::assertSame('New pain', $scenario->brief(BriefSection::Pain));
        self::assertSame('', $scenario->brief(BriefSection::Budget));
        self::assertSame('On the train', $scenario->setup());
    }
}
