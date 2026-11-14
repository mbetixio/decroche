<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Controller\SecurityController;
use App\Demo\DemoCalls;
use App\Entity\Call;
use App\Entity\Scenario;
use App\Enum\Language;
use App\EventListener\LocaleListener;
use App\Kernel;
use App\Repository\CallRepository;
use App\Repository\MemberRepository;
use App\Security\McpKeyAuthenticator;
use App\Security\Voter\CallVoter;
use App\Security\Voter\ScenarioVoter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The guards around the pages: who may see a call or a scenario, the routes the firewall answers itself, the language
 * of internal routes, password rehashing, and an onboarded instance's onboarding pages.
 */
final class GuardsTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
    }

    public function testNobodySignedInSeesNoCallAndNoScenario(): void
    {
        $this->service(DemoCalls::class)->load(Language::English, $this->manager());
        $call = $this->service(CallRepository::class)->all()[0];

        self::assertSame(VoterInterface::ACCESS_DENIED, new CallVoter()->vote(new NullToken(), $call, [CallVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, new ScenarioVoter()->vote(new NullToken(), $call->scenario, [ScenarioVoter::VIEW]));
        self::assertInstanceOf(Call::class, $call);
        self::assertInstanceOf(Scenario::class, $call->scenario);
    }

    public function testTheFirewallAnswersTheSignOutAndTheSignInLinks(): void
    {
        foreach (['logout', 'loginLink'] as $method) {
            try {
                new SecurityController()->{$method}();
                self::fail($method.' is never reached.');
            } catch (\LogicException $e) {
                self::assertSame('Intercepted by the firewall.', $e->getMessage());
            }
        }
    }

    public function testTheMcpEndpointExplainsHowToConnect(): void
    {
        $response = new McpKeyAuthenticator($this->service(MemberRepository::class))->start(Request::create('/mcp'));

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('/mcp?key=', (string) $response->getContent());
    }

    public function testOnlyTheKnownEnvironmentsBoot(): void
    {
        $kernel = new Kernel('staging', false);
        try {
            $this->expectExceptionMessage('The environment "staging" is not registered as allowed');
            $kernel->boot();
        } finally {
            new Filesystem()->remove($kernel->getCacheDir());
        }
    }

    public function testInternalRoutesKeepTheirLanguage(): void
    {
        $request = Request::create('/_wdt/abc');
        $request->setLocale('de');
        $this->service(LocaleListener::class)(new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('de', $request->getLocale());
    }

    public function testAPasswordIsRehashedForMembersOnly(): void
    {
        $members = $this->service(MemberRepository::class);
        $member = $this->member();
        $members->upgradePassword($member, 'a-new-hash');
        self::assertSame('a-new-hash', $members->findOneByEmail('seller@decroche.test')?->getPassword());

        $this->expectException(UnsupportedUserException::class);
        $members->upgradePassword(new InMemoryUser('someone', 'hash'), 'another-hash');
    }

    public function testAnOnboardedInstanceLeavesTheOnboarding(): void
    {
        $this->signIn($this->manager());
        $this->client->request('GET', '/welcome/company');
        self::assertResponseRedirects('/');
    }

    public function testTheTeamBoardFollowsTheMissionsAgainstTheCalls(): void
    {
        $manager = $this->manager();
        $this->service(DemoCalls::class)->load(Language::English, $manager);
        $this->signIn($manager);
        $seller = $this->service(MemberRepository::class)->findOneByEmail('jordan.lee@decroche.test');
        self::assertNotNull($seller);

        $this->client->request('GET', '/team');
        $this->submit('Give the mission', ['mission[assignee]' => (string) $seller->id, 'mission[situation]' => 'gatekeeper', 'mission[calls]' => '2', 'mission[target]' => '50']);
        $this->client->followRedirect();
        // Only the calls made since the mission count: the earlier ones are history.
        self::assertSelectorTextContains('.progress-list', 'Jordan Lee 0/2');
    }
}
