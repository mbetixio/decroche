<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\Language;
use App\Repository\CompanyRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Every page speaks the company's working language. Before it is chosen, on the first onboarding screen, a "lang"
 * query parameter previews the other language.
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
        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }
        $company = $this->companies->get();
        $preview = $company->isOnboarded() ? null : Language::tryFrom($request->query->getString('lang'));
        $request->setLocale(($preview ?? $company->language)->value);
    }
}
