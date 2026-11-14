<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use App\Repository\MemberRepository;

/**
 * The first boot: every visit leads to the onboarding, which goes step by step from the language to the first call.
 */
final class OnboardingTest extends FunctionalTestCase
{
    public function testTheFirstVisitOpensTheOnboarding(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseRedirects('/welcome');
    }

    public function testTheLanguageCanBePreviewedBeforeChoosing(): void
    {
        $this->client->request('GET', '/welcome?lang=fr');
        self::assertSelectorTextContains('h1', 'Entraînez vos commerciaux');
        $this->client->request('GET', '/welcome?lang=en');
        self::assertSelectorTextContains('h1', 'Train your sellers');
    }

    public function testStepsCannotBeSkipped(): void
    {
        $this->client->request('GET', '/welcome/offer');
        self::assertResponseRedirects();
        self::assertStringNotContainsString('/offer', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAManagerSetsTheInstanceUpWithTheExampleCompany(): void
    {
        $this->client->request('GET', '/welcome?lang=en');
        $this->submit('Get started', ['language[language]' => 'en']);
        self::assertResponseRedirects('/welcome/account');

        $this->client->followRedirect();
        $this->submit('Create my account', ['account[name]' => 'Morgan Lee', 'account[email]' => 'morgan@decroche.test', 'account[password]' => 'password-123', 'account[profile]' => 'Sales manager.']);
        self::assertResponseRedirects('/welcome/company');
        self::assertTrue($this->service(MemberRepository::class)->hasAny());

        $this->client->followRedirect();
        $this->submit('Load the example', ['company[name]' => 'Acme']);
        self::assertResponseRedirects('/welcome/offer');
        // Finishing from a step before the last one only leads back to it.
        $this->client->request('POST', '/welcome/finish', ['_token' => $this->csrfToken('onboarding-finish')]);
        self::assertResponseRedirects('/welcome/offer');
        self::assertFalse($this->service(CompanyRepository::class)->get()->isOnboarded());

        foreach (['situations' => 'Continue', 'scorecard' => 'Continue', 'team' => 'Continue', 'done' => 'Later'] as $next => $button) {
            $this->client->followRedirect();
            $this->submit($button);
            self::assertResponseRedirects('/welcome/'.$next);
        }
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'all set');

        $this->submit('Go to the home page');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ready for a call?');
        $company = $this->service(CompanyRepository::class)->get();
        self::assertTrue($company->isOnboarded());
        self::assertSame(OnboardingStep::Done, $company->step);
    }

    public function testTheWebsiteAnalysisWritesTheOfferAndTheSituations(): void
    {
        $this->client->request('GET', '/welcome?lang=en');
        $this->submit('Get started', ['language[language]' => 'en']);
        $this->client->followRedirect();
        $this->submit('Create my account', ['account[name]' => 'Morgan Lee', 'account[email]' => 'morgan@decroche.test', 'account[password]' => 'password-123', 'account[profile]' => 'Sales manager.']);
        $this->client->followRedirect();
        $this->submit('Analyse', ['company[name]' => 'Acme', 'company[about]' => 'Acme builds software for retailers and logistics firms across Europe.']);
        self::assertResponseRedirects('/welcome/analysis');

        $this->client->followRedirect();
        $token = $this->client->getCrawler()->filter('[data-analysis-token-value]')->attr('data-analysis-token-value');
        $this->client->request('POST', '/welcome/analysis/run', ['token' => $token], server: ['HTTP_ACCEPT' => 'application/x-ndjson']);
        self::assertResponseIsSuccessful();
        $lines = $this->lines();
        self::assertSame(['t' => 'done', 'url' => '/welcome/offer'], end($lines));

        $company = $this->service(CompanyRepository::class)->get();
        self::assertStringContainsString('Dedicated teams', $company->offer);
        self::assertCount(5, $company->situations());
    }
}
