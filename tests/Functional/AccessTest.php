<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\Language;
use App\Enum\MemberRole;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Who may go where: sign-in, the managers' pages, the profile asked before playing, every page rendering.
 */
final class AccessTest extends FunctionalTestCase
{
    public function testVisitorsSignInFirst(): void
    {
        $this->onboardedCompany();
        $this->manager();
        $this->client->request('GET', '/progress');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAMemberSignsInWithTheirPassword(): void
    {
        $this->onboardedCompany();
        $this->member();
        $this->client->request('GET', '/login');
        $this->submit('Sign in', ['_username' => 'seller@decroche.test', '_password' => 'password-123']);
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ready for a call?');
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->onboardedCompany();
        $this->member();
        $this->client->request('GET', '/login');
        $this->submit('Sign in', ['_username' => 'seller@decroche.test', '_password' => 'wrong']);
        $this->client->followRedirect();
        self::assertSelectorExists('.flash.error');
    }

    public function testRepeatedWrongPasswordsLockTheAccountForAWhile(): void
    {
        $this->onboardedCompany();
        $this->member();
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('GET', '/login');
            $this->submit('Sign in', ['_username' => 'seller@decroche.test', '_password' => 'wrong']);
        }
        // Even the right password waits now.
        $this->client->request('GET', '/login');
        $this->submit('Sign in', ['_username' => 'seller@decroche.test', '_password' => 'password-123']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Too many failed login attempts');
    }

    public function testSellersCannotOpenTheManagersPages(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->member());
        foreach (['/team', '/settings', '/settings/situations', '/library/new'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
    }

    public function testAMemberWithoutProfileDescribesThemselfBeforePlaying(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->member(profile: ''));
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/me');
        $this->client->followRedirect();
        $this->submit('Let\'s go', ['profile[profile]' => 'Seller for a year, I call retailers.']);
        self::assertResponseRedirects('/');
    }

    /**
     * Every page of the site renders for a manager, in both working languages, without a translation key left on
     * screen.
     */
    #[DataProvider('languages')]
    public function testEveryPageRendersTranslated(Language $language): void
    {
        $this->onboardedCompany($language);
        $this->signIn($this->member('boss@decroche.test', MemberRole::Manager));
        foreach (['/', '/library', '/library/1', '/library/1/edit', '/library/new', '/prepare', '/progress', '/offer', '/calls', '/team', '/settings', '/settings/situations', '/settings/scorecard', '/account', '/me', '/call/1'] as $page) {
            $crawler = $this->client->request('GET', $page);
            self::assertResponseIsSuccessful($page);
            self::assertDoesNotMatchRegularExpression('/\\b(nav|action|play|call|briefing|debrief|progress|team|settings|account|library|prepare|offer|form|enum|level|security|onboarding)\\.[a-z_]+(\\.[a-z_0-9]+)*\\b/', $crawler->filter('body')->text(), $page.' shows a translation key.');
        }
    }

    /**
     * @return iterable<string, array{Language}>
     */
    public static function languages(): iterable
    {
        yield 'English' => [Language::English];
        yield 'French' => [Language::French];
    }
}
