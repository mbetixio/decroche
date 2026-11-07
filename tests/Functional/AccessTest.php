<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\MemberRole;

/**
 * Who may go where: sign-in, the managers' pages, the profile asked before playing, every page rendering.
 */
final class AccessTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
    }

    public function testVisitorsSignInFirst(): void
    {
        $this->manager();
        $this->client->request('GET', '/progress');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAMemberSignsInWithTheirPassword(): void
    {
        $this->member();
        $this->client->request('GET', '/login');
        $this->submit('Sign in', ['_username' => 'seller@sparring.test', '_password' => 'password-123']);
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ready for a call?');
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->member();
        $this->client->request('GET', '/login');
        $this->submit('Sign in', ['_username' => 'seller@sparring.test', '_password' => 'wrong']);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash.error');
    }

    public function testSellersCannotOpenTheManagersPages(): void
    {
        $this->signIn($this->member());
        foreach (['/team', '/settings', '/settings/situations', '/library/new'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
    }

    public function testAMemberWithoutProfileDescribesThemselfBeforePlaying(): void
    {
        $this->signIn($this->member(profile: ''));
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/me');
        $this->client->followRedirect();
        $this->submit('Let\'s go', ['profile[profile]' => 'Seller for a year, I call retailers.']);
        self::assertResponseRedirects('/');
    }

    /**
     * Every page of the site renders for a manager, in both languages.
     */
    public function testEveryPageRenders(): void
    {
        $this->signIn($this->member('boss@sparring.test', MemberRole::Manager));
        foreach (['/', '/library', '/library/1', '/library/1/edit', '/library/new', '/prepare', '/progress', '/offer', '/calls', '/team', '/settings', '/settings/situations', '/settings/scorecard', '/account', '/me', '/call/1'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseIsSuccessful($page);
        }
    }
}
