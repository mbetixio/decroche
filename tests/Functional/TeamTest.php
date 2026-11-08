<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Enum\MemberRole;
use App\Repository\InvitationRepository;
use App\Repository\MemberRepository;
use App\Repository\MissionRepository;
use App\Repository\ScenarioRepository;

/**
 * The managers' side: invitations and joining, roles, missions, the library and the company settings.
 */
final class TeamTest extends FunctionalTestCase
{
    private Member $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->manager = $this->signIn($this->manager());
    }

    public function testAnInvitedSellerJoinsTheTeam(): void
    {
        $this->client->request('GET', '/team');
        $this->submit('Create the links', ['invitations[emails]' => "alex@practicall.test, not-an-email\ncamille@practicall.test"]);
        self::assertResponseRedirects();
        $invitations = $this->service(InvitationRepository::class)->pending();
        self::assertCount(2, $invitations);

        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/join/'.$invitations[0]->token);
        self::assertSelectorTextContains('h1', 'Join');
        $this->submit('Join the team', ['join[name]' => 'Alex Martin', 'join[password]' => 'password-123']);
        self::assertResponseRedirects('/me');
        self::assertNotNull($this->service(MemberRepository::class)->findOneByEmail($invitations[0]->email));

        $this->client->request('GET', '/join/'.$invitations[0]->token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAManagerGivesAMission(): void
    {
        $seller = $this->member();
        $this->client->request('GET', '/team');
        $this->submit('Give the mission', ['mission[assignee]' => (string) $seller->id, 'mission[situation]' => 'price_request', 'mission[calls]' => '3', 'mission[target]' => '70']);
        self::assertResponseRedirects();
        $seller = $this->service(MemberRepository::class)->findOneByEmail('seller@practicall.test');
        $missions = $this->service(MissionRepository::class)->forMember($seller);
        self::assertCount(1, $missions);
        self::assertSame('price_request', $missions[0]->situation);

        $this->signIn($seller);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('main', 'My missions');
    }

    public function testAManagerCannotChangeTheirOwnRole(): void
    {
        $seller = $this->member();
        $crawler = $this->client->request('GET', '/team/'.$seller->id);
        $this->client->submit($crawler->filter('form[action$="/role"]')->form());
        self::assertSame(MemberRole::Manager, $this->service(MemberRepository::class)->findOneByEmail('seller@practicall.test')?->role);
        self::assertSelectorNotExists('form[action="/team/'.$this->manager->id.'/role"]');
    }

    public function testAManagerWritesAndArchivesAScenario(): void
    {
        $this->client->request('GET', '/library/new');
        $this->submit('Save', ['scenario' => [
            'title' => 'The CFO who wants a discount', 'type' => 'negotiation', 'difficulty' => '3', 'minutes' => '8',
            'context' => 'The CFO calls about the renewal.', 'goal' => 'Renew without discount.', 'personaName' => 'Paul Durand',
            'personaRole' => 'CFO', 'personaCompany' => 'Acme', 'personaPersonality' => 'Cold, precise.', 'opening' => 'Paul Durand. About the renewal.',
            'brief' => ['pain' => 'His budget was cut by 20%.'],
        ]]);
        self::assertResponseRedirects();
        $scenario = $this->service(ScenarioRepository::class)->findOneBy(['title' => 'The CFO who wants a discount']);
        self::assertNotNull($scenario);

        $this->client->request('GET', '/library/'.$scenario->id);
        $this->submit('Archive');
        self::assertFalse($this->service(ScenarioRepository::class)->find($scenario->id)?->active);
    }

    public function testTheCompanySettingsAreSaved(): void
    {
        $this->client->request('GET', '/settings');
        $this->client->submit($this->client->getCrawler()->filter('form[name=offer]')->form(), ['offer[offer]' => 'We build dedicated software teams for retailers and logistics firms, with fixed-price framing workshops and technical audits.', 'offer[sectors]' => "retail\nlogistics"]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Saved.');
    }
}
