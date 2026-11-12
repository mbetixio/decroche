<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Coach;

use App\Call\Coach\CoachPrompt;
use App\Call\Speech\SpeechFigures;
use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Enum\Language;
use App\Enum\Speaker;
use App\Model\Criterion;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The coach's prompts: the scorecard, the offer, the language and the seller's profile in the system prompt; the
 * scenario, the brief, the transcript, the figures and how the call ended in the user prompt.
 */
final class CoachPromptTest extends TestCase
{
    use Fixtures;

    public function testSystem(): void
    {
        $company = self::company(Language::French);
        $company->setScorecard([new Criterion('rapport', 'Rapport', 'builds trust'), new Criterion('closing', 'Closing')]);
        $member = self::member();
        $member->profile = '  Junior seller, sells dev teams. ';
        $prompt = new CoachPrompt()->system(self::call(member: $member), $company);

        self::assertStringContainsString('a seller from Etix', $prompt);
        self::assertStringContainsString('Dedicated development teams.', $prompt);
        self::assertStringContainsString("- rapport: Rapport (builds trust)\n- closing: Closing\n", $prompt);
        self::assertStringContainsString('{"criteria": [{"key": "rapport", "score": 0', $prompt);
        self::assertStringContainsString('the 2 of them', $prompt);
        self::assertStringContainsString('Every text value is written in French', $prompt);
        self::assertStringContainsString('previous_debriefs', $prompt);
        self::assertStringContainsString('The seller describes themself this way (fit your advice to their experience and what they sell): Junior seller, sells dev teams.', $prompt);
    }

    public function testSystemWithoutProfileUsesTheDefaultScorecard(): void
    {
        $prompt = new CoachPrompt()->system(self::call(), self::company(Language::English));

        self::assertStringNotContainsString('describes themself', $prompt);
        self::assertStringContainsString('- discovery: Needs discovery (', $prompt);
        self::assertStringContainsString('the 6 of them', $prompt);
    }

    public function testUser(): void
    {
        $scenario = self::scenario(brief: ['pain' => 'Lead dev leaving', 'budget' => '50k', 'setup' => 'On the train']);
        $scenario->setObjectives(['Understand the need', 'Get a meeting']);
        $scenario->setSituation(self::situation());
        $call = self::call($scenario);
        $call->say(Speaker::Seller, 'What is your budget?', self::at('2026-11-13 10:01:00'));
        $call->say(Speaker::Persona, 'About 50k.', self::at('2026-11-13 10:01:10'));
        $call->reveal(BriefSection::Budget);
        $call->stop(CallEnd::Clock, self::at('2026-11-13 10:04:00'));
        $figures = new SpeechFigures(false, 50, 4, 5, 1, 1, 0, [], 4, null, 50);
        $prompt = new CoachPrompt()->user($call, $figures, self::at('2026-11-13 11:00:00'));

        self::assertStringContainsString('The scenario: "Discovery with Acme" (a discovery meeting, difficulty 2/3).', $prompt);
        self::assertStringContainsString('The client: Claire Dubois, CTO at Acme.', $prompt);
        self::assertStringContainsString("1. Understand the need\n2. Get a meeting", $prompt);
        self::assertStringContainsString('It is a phone call: Price request on the website.', $prompt);
        self::assertStringContainsString("The situation from the client's side: On the train", $prompt);
        self::assertStringContainsString('- [budget] Real budget: 50k (the client gave it away)', $prompt);
        self::assertStringContainsString("- [pain] Real pain: Lead dev leaving\n", $prompt);
        self::assertStringContainsString("CLIENT: Hello, Claire speaking.\nSELLER: What is your budget?\nCLIENT: About 50k.", $prompt);
        self::assertStringContainsString('(4 min out of 10)', $prompt);
        self::assertStringContainsString($figures->describe(), $prompt);
        self::assertStringContainsString('The clock ended the meeting', $prompt);
    }

    public function testUserHowTheCallEnded(): void
    {
        $figures = new SpeechFigures(false, 0, 0, 3, 0, 0, 0, [], 0, null, null);
        $byPersona = self::call();
        $byPersona->stop(CallEnd::Persona, self::at());
        $bySeller = self::call();
        $bySeller->stop(CallEnd::Seller, self::at());

        self::assertStringContainsString('The client ended the meeting.', new CoachPrompt()->user($byPersona, $figures, self::at()));
        self::assertStringContainsString('The seller ended the meeting.', new CoachPrompt()->user($bySeller, $figures, self::at()));
        self::assertStringNotContainsString('phone call', new CoachPrompt()->user($bySeller, $figures, self::at()));
        self::assertStringNotContainsString('objectives of the call', new CoachPrompt()->user($bySeller, $figures, self::at()));
    }
}
