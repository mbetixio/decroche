<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Entity\Member;
use App\Repository\CompanyRepository;
use App\Repository\MemberRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Steers every visit while the instance is being set up, then makes sure members introduce themselves before playing:
 *
 * - before anyone has an account, every page leads to the onboarding (its public steps);
 * - during the onboarding, managers are taken back to it, sellers wait for it to finish;
 * - once it is done, the onboarding is closed, and a member who has not described themself is asked to before playing
 *   (their calls adapt to it).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class OnboardingGuard
{
    /** Pages always open: signing in and out, joining by invitation. */
    private const array ALWAYS_OPEN = ['login', 'logout', 'login_link_check', 'join'];

    /** Pages where one plays: a member must have described themself first. */
    private const array PLAY = ['home', 'library', 'call_draw', 'call_briefing', 'scenario_start', 'prepare', 'call_talk'];

    public function __construct(
        private CompanyRepository $companies,
        private MemberRepository $members,
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->getString('_route');
        if (!$event->isMainRequest() || '' === $route || str_starts_with($route, '_') || \in_array($route, self::ALWAYS_OPEN, true) || str_starts_with($request->getPathInfo(), '/mcp')) {
            return;
        }
        $target = $this->target($route);
        if (null !== $target && $target !== $request->getPathInfo()) {
            $event->setResponse(new RedirectResponse($target));
        }
    }

    /**
     * Where this visit should go instead, or null to let it through.
     */
    private function target(string $route): ?string
    {
        $company = $this->companies->get();
        $onboarding = str_starts_with($route, 'onboarding');
        $member = $this->security->getUser();

        if ($company->isOnboarded()) {
            if ($onboarding) {
                return $this->urls->generate('home');
            }

            return $member instanceof Member && !$member->hasProfile() && \in_array($route, self::PLAY, true) ? $this->urls->generate('profile') : null;
        }

        if (!$this->members->hasAny()) {
            return $onboarding ? null : $this->urls->generate('onboarding', ['step' => OnboardingStep::Welcome->value]);
        }
        if (!$member instanceof Member) {
            return $this->urls->generate('login');
        }
        if (!$member->isManager()) {
            return 'onboarding_waiting' === $route ? null : $this->urls->generate('onboarding_waiting');
        }

        return $onboarding ? null : $this->urls->generate('onboarding', ['step' => $company->step->value]);
    }
}
