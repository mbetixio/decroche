<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Company;
use App\Enum\Language;
use App\Model\Criterion;
use App\Onboarding\OnboardingStep;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The company: its catalogue of situations, its sectors, its scorecard (Décroche's or its own), readiness and the
 * onboarding steps it reached.
 */
final class CompanyTest extends TestCase
{
    use Fixtures;

    public function testANewCompanyHasNothing(): void
    {
        $company = new Company();

        self::assertSame([], $company->situations());
        self::assertNull($company->situation('price_request'));
        self::assertNull($company->situation(null));
        self::assertFalse($company->isReady());
        self::assertFalse($company->isOnboarded());
        self::assertSame(OnboardingStep::Welcome, $company->step);
    }

    public function testSetSituations(): void
    {
        $company = new Company();
        $company->setSituations([self::situation('price_request'), self::situation('cold_call')]);

        self::assertSame(['price_request', 'cold_call'], array_keys($company->situations()));
        self::assertSame('Price request on the website', $company->situation('cold_call')?->label);

        $company->setSituations([self::situation('gatekeeper')]);
        self::assertSame(['gatekeeper'], array_keys($company->situations()));
    }

    public function testSetSituationsMakesKeysUnique(): void
    {
        $company = new Company();
        $company->setSituations([self::situation('price_request'), self::situation('price_request')]);

        self::assertSame(['price_request', 'price_request_2'], array_keys($company->situations()));
    }

    public function testAddMissingSituationsKeepsExistingOnes(): void
    {
        $company = new Company();
        $company->setSituations([self::situation('price_request', ['Mine'])]);
        $company->addMissingSituations([self::situation('price_request', ['Theirs']), self::situation('cold_call')]);
        $situations = $company->situations();

        self::assertSame(['price_request', 'cold_call'], array_keys($situations));
        self::assertSame(['Mine'], $situations['price_request']->objectives);
    }

    public function testIsReady(): void
    {
        $company = new Company();
        $company->offer = '   ';
        $company->setSituations([self::situation()]);
        self::assertFalse($company->isReady());

        $company->offer = 'Dedicated teams';
        self::assertTrue($company->isReady());

        $company->setSituations([]);
        self::assertFalse($company->isReady());
    }

    public function testSetSectors(): void
    {
        $company = new Company();
        $company->setSectors([' Retail ', 'retail', 'Retail', '', 'Banking']);

        self::assertSame(['Retail', 'retail', 'Banking'], $company->sectors);
    }

    public function testDefaultScorecardFollowsTheLanguage(): void
    {
        $company = new Company();
        self::assertEquals(Criterion::defaults(Language::French), $company->scorecard());

        $company->language = Language::English;
        self::assertEquals(Criterion::defaults(Language::English), $company->scorecard());
    }

    public function testOwnScorecard(): void
    {
        $company = new Company();
        $company->setScorecard([new Criterion('rapport', 'Rapport', 'trust'), new Criterion('closing', 'Closing')]);

        self::assertEquals(['rapport' => new Criterion('rapport', 'Rapport', 'trust'), 'closing' => new Criterion('closing', 'Closing', '')], $company->scorecard());
    }

    public function testScorecardNeedsTwoCriteriaAndKeepsEightAtMost(): void
    {
        $company = new Company();
        $company->setScorecard([new Criterion('alone', 'Alone')]);
        self::assertEquals(Criterion::defaults(), $company->scorecard());

        $company->setScorecard(array_map(static fn (int $i): Criterion => new Criterion("c{$i}", "C{$i}"), range(1, 10)));
        self::assertCount(8, $company->scorecard());
        self::assertSame('c8', array_key_last($company->scorecard()));
    }

    public function testReachNeverGoesBack(): void
    {
        $company = new Company();
        $company->reach(OnboardingStep::Offer);
        self::assertSame(OnboardingStep::Offer, $company->step);

        $company->reach(OnboardingStep::Account);
        self::assertSame(OnboardingStep::Offer, $company->step);
    }

    public function testHasReached(): void
    {
        $company = new Company();
        $company->reach(OnboardingStep::Analysis);

        self::assertTrue($company->hasReached(OnboardingStep::Welcome));
        self::assertTrue($company->hasReached(OnboardingStep::Analysis));
        self::assertFalse($company->hasReached(OnboardingStep::Offer));
    }

    public function testCompleteOnboarding(): void
    {
        $company = new Company();
        $company->completeOnboarding(self::at());

        self::assertTrue($company->isOnboarded());
        self::assertSame(OnboardingStep::Done, $company->step);
        self::assertEquals(self::at(), $company->onboardedAt);
        self::assertTrue($company->hasReached(OnboardingStep::Team));
    }
}
