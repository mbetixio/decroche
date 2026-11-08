<?php

declare(strict_types=1);

namespace App\Tests\Unit\Onboarding;

use App\Onboarding\OnboardingStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The onboarding steps: their order, the step before and after, which ones need no account.
 */
final class OnboardingStepTest extends TestCase
{
    public function testIndexFollowsTheOrder(): void
    {
        foreach (OnboardingStep::cases() as $i => $step) {
            self::assertSame($i, $step->index());
        }
        self::assertSame(0, OnboardingStep::Welcome->index());
        self::assertSame(8, OnboardingStep::Done->index());
    }

    public function testNextAndPrevious(): void
    {
        self::assertSame(OnboardingStep::Account, OnboardingStep::Welcome->next());
        self::assertSame(OnboardingStep::Analysis, OnboardingStep::Company->next());
        self::assertSame(OnboardingStep::Done, OnboardingStep::Team->next());
        self::assertNull(OnboardingStep::Done->next());

        self::assertNull(OnboardingStep::Welcome->previous());
        self::assertSame(OnboardingStep::Welcome, OnboardingStep::Account->previous());
        self::assertSame(OnboardingStep::Team, OnboardingStep::Done->previous());
    }

    public function testNextThenPreviousComesBack(): void
    {
        foreach (OnboardingStep::cases() as $step) {
            if (null !== $next = $step->next()) {
                self::assertSame($step, $next->previous());
            }
        }
    }

    public function testOnlyWelcomeAndAccountArePublic(): void
    {
        $public = array_values(array_filter(OnboardingStep::cases(), static fn (OnboardingStep $s): bool => $s->isPublic()));

        self::assertSame([OnboardingStep::Welcome, OnboardingStep::Account], $public);
    }

    public function testTranslationKey(): void
    {
        self::assertSame('onboarding.step.scorecard', OnboardingStep::Scorecard->trans(new IdentityTranslator()));
    }
}
