<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Persona;

use App\Call\Persona\PersonaPrompt;
use App\Enum\BriefSection;
use App\Enum\Language;
use App\Enum\Speaker;
use App\Enum\Tone;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The persona's system prompt: who they are, the language, the brief sections (and which were already revealed), the
 * phone situation, the time left, the voice and the extra note.
 */
final class PersonaPromptTest extends TestCase
{
    use Fixtures;

    public function testWhoTheyAreAndTheLanguage(): void
    {
        $call = self::call(self::scenario(persona: self::persona(Tone::Wary)));
        $prompt = new PersonaPrompt()->build($call, self::company(Language::English), self::at());

        self::assertStringContainsString('You are Claire Dubois, CTO at Acme.', $prompt);
        self::assertStringContainsString('Your personality: Pragmatic and busy.', $prompt);
        self::assertStringContainsString('Your state of mind during this call: '.Tone::Wary->describe(), $prompt);
        self::assertStringContainsString('Speak English only', $prompt);
        self::assertStringContainsString('Dedicated development teams.', $prompt);
        self::assertStringContainsString('you are meeting a seller from Etix', $prompt);
        self::assertStringContainsString(\App\Enum\Difficulty::Demanding->instruction(), $prompt);

        $french = new PersonaPrompt()->build($call, self::company(Language::French), self::at());
        self::assertStringContainsString('Speak French only', $french);
    }

    public function testTheBriefSections(): void
    {
        $call = self::call(self::scenario(brief: ['pain' => 'Lead dev leaving', 'budget' => '50k a year', 'setup' => 'HIDDEN-SETUP']));
        $call->reveal(BriefSection::Budget);
        $prompt = new PersonaPrompt()->build($call, self::company(), self::at());

        self::assertStringContainsString('- [budget] Real budget: 50k a year (already revealed: you may come back to it freely)', $prompt);
        self::assertStringContainsString("- [pain] Real pain: Lead dev leaving\n", $prompt);
        self::assertStringNotContainsString('[secret]', $prompt);
        self::assertStringNotContainsString('HIDDEN-SETUP', $prompt, 'the setup is only told for a phone situation');
        self::assertSame(1, substr_count($prompt, 'already revealed'));
    }

    public function testAPhoneSituation(): void
    {
        $scenario = self::scenario(brief: ['pain' => 'Late project', 'setup' => 'You are on the train.']);
        $scenario->setSituation(self::situation());
        $scenario->material = 'The form message: how much?';
        $prompt = new PersonaPrompt()->build(self::call($scenario), self::company(), self::at());

        self::assertStringContainsString('The situation: a phone call: a seller from Etix is calling you (Price request on the website).', $prompt);
        self::assertStringContainsString('What you know and are going through on your side: You are on the train.', $prompt);
        self::assertStringContainsString('The form message: how much?', $prompt);
        self::assertStringContainsString('It is a phone call', $prompt);
    }

    public function testTimeLeft(): void
    {
        $call = self::call(self::scenario(minutes: 10));
        $prompter = new PersonaPrompt();

        $early = $prompter->build($call, self::company(), self::at('2026-11-13 10:01:00'));
        self::assertStringContainsString('About 9 minute(s) of conversation remain.', $early);
        self::assertStringNotContainsString('soon have to hang up', $early);

        $late = $prompter->build($call, self::company(), self::at('2026-11-13 10:08:30'));
        self::assertStringContainsString('About 2 minute(s) of conversation remain: you will soon have to hang up', $late);
    }

    public function testVoiceAndNote(): void
    {
        $call = self::call();
        $typed = new PersonaPrompt()->build($call, self::company(), self::at());
        self::assertStringNotContainsString('talking to you out loud', $typed);

        $call->say(Speaker::Seller, 'hello', self::at(), 1500);
        $spoken = new PersonaPrompt()->build($call, self::company(), self::at(), PersonaPrompt::INTERRUPTED);
        self::assertStringContainsString('The seller is talking to you out loud', $spoken);
        self::assertStringContainsString('read aloud in English', $spoken);
        self::assertStringEndsWith('- '.PersonaPrompt::INTERRUPTED, $spoken);
    }
}
