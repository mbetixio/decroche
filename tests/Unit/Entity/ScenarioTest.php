<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Enum\BriefSection;
use App\Enum\MemberRole;
use App\Enum\Tone;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A scenario: its persona, its hidden brief and setup, its situation and objectives, who may see or edit it, and its
 * public view, which never gives the brief away.
 */
final class ScenarioTest extends TestCase
{
    use Fixtures;

    public function testBriefAndSetup(): void
    {
        $scenario = self::scenario(brief: ['pain' => ' Lead dev leaving ', 'budget' => '50k', 'setup' => 'Between two meetings']);

        self::assertSame('Lead dev leaving', $scenario->brief(BriefSection::Pain));
        self::assertSame('50k', $scenario->brief(BriefSection::Budget));
        self::assertSame('', $scenario->brief(BriefSection::Secret));
        self::assertSame('Between two meetings', $scenario->setup());
    }

    public function testSetBriefDropsBlanks(): void
    {
        $scenario = self::scenario();
        $scenario->setBrief(['pain' => 'Late project', 'budget' => '   ', 'secret' => '', 'yes' => "\tA pilot\n"]);

        self::assertSame(['pain' => 'Late project', 'yes' => 'A pilot'], $scenario->briefArray());
        self::assertSame('', $scenario->setup());
        self::assertSame('', $scenario->brief(BriefSection::Budget));
    }

    public function testPersona(): void
    {
        $scenario = self::scenario(persona: self::persona(Tone::Wary, 'f'));
        self::assertEquals(self::persona(Tone::Wary, 'f'), $scenario->persona());

        $scenario->setPersona(self::persona(name: 'Paul Martin'));
        self::assertSame('Paul Martin', $scenario->persona()->name);
        self::assertNull($scenario->persona()->tone);
    }

    public function testSituation(): void
    {
        $scenario = self::scenario();
        self::assertNull($scenario->situation());

        $scenario->setSituation(self::situation('price_request'));
        self::assertEquals(self::situation('price_request'), $scenario->situation());

        $scenario->setSituation(null);
        self::assertNull($scenario->situation());
    }

    public function testObjectivesAreCleanedAndCut(): void
    {
        $scenario = self::scenario();
        self::assertSame([], $scenario->objectives());

        $scenario->setObjectives([' - One', 'Two', 'Two', '', 'Three', 'Four', 'Five']);
        self::assertSame(['One', 'Two', 'Three', 'Four'], $scenario->objectives());
    }

    public function testALibraryScenarioIsVisibleToEveryone(): void
    {
        $seller = self::member();

        self::assertTrue(self::scenario()->visibleTo($seller));
        self::assertTrue(self::scenario(self::member('Boss', MemberRole::Manager, 'boss@example.com'))->visibleTo($seller));
    }

    public function testASellersScenarioStaysTheirsAndTheirManagers(): void
    {
        $author = self::member();
        $other = self::member('Bob', email: 'bob@example.com');
        $manager = self::member('Boss', MemberRole::Manager, 'boss@example.com');
        $scenario = self::scenario($author);

        self::assertTrue($scenario->visibleTo($author));
        self::assertTrue($scenario->visibleTo($manager));
        self::assertFalse($scenario->visibleTo($other));
    }

    public function testEditableBy(): void
    {
        $author = self::member();
        $other = self::member('Bob', email: 'bob@example.com');
        $manager = self::member('Boss', MemberRole::Manager, 'boss@example.com');

        self::assertTrue(self::scenario($author)->editableBy($author));
        self::assertTrue(self::scenario($author)->editableBy($manager));
        self::assertFalse(self::scenario($author)->editableBy($other));
        self::assertTrue(self::scenario()->editableBy($manager));
        self::assertFalse(self::scenario()->editableBy($author));
        self::assertFalse(self::scenario($manager)->editableBy($author));
    }

    public function testPublicViewOfALibraryScenario(): void
    {
        $view = self::scenario()->publicView();

        self::assertSame([
            'id' => null,
            'title' => 'Discovery with Acme',
            'type' => 'discovery',
            'difficulty' => 2,
            'minutes' => 10,
            'context' => 'Acme filled in the contact form.',
            'goal' => 'Get a second meeting.',
            'persona' => ['name' => 'Claire Dubois', 'role' => 'CTO', 'company' => 'Acme'],
        ], $view);
    }

    public function testPublicViewOfASurpriseCall(): void
    {
        $scenario = self::scenario();
        $scenario->setSituation(self::situation());
        $scenario->material = 'Name: Claire. Message: how much?';
        $scenario->setObjectives(['Understand the need']);
        $view = $scenario->publicView();

        self::assertSame('Price request on the website', $view['situation']);
        self::assertSame('outgoing', $view['direction']);
        self::assertSame('Name: Claire. Message: how much?', $view['material']);
        self::assertSame(['Understand the need'], $view['objectives']);
    }

    public function testPublicViewNeverContainsTheBrief(): void
    {
        $scenario = self::scenario(brief: ['pain' => 'SECRET-PAIN', 'budget' => 'SECRET-BUDGET', 'decision' => 'SECRET-DECISION', 'objections' => 'SECRET-OBJECTIONS', 'yes' => 'SECRET-YES', 'secret' => 'SECRET-SECRET', 'setup' => 'SECRET-SETUP']);
        $scenario->setSituation(self::situation());
        $json = json_encode($scenario->publicView(), \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('SECRET', $json);
        self::assertStringNotContainsString('Pragmatic and busy', $json, 'nor the persona\'s personality');
        self::assertArrayNotHasKey('brief', $scenario->publicView());
    }
}
