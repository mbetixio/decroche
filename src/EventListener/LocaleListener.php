<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\Language;
use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Every page speaks the company's working language. Until it is chosen (the first onboarding screen), pages speak
 * English, and a "lang" query parameter previews the other language.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class LocaleListener
{
    public function __construct(
        private CompanyRepository $companies,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }
        $company = $this->companies->get();
        if ($company->hasReached(OnboardingStep::Account)) {
            $request->setLocale($company->language->value);

            return;
        }
        $request->setLocale((Language::tryFrom($request->query->getString('lang')) ?? Language::English)->value);
    }
}
