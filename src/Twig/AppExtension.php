<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * What every template may need: the company, and a short duration in minutes and seconds.
 */
final readonly class AppExtension
{
    public function __construct(
        private CompanyRepository $companies,
    ) {
    }

    #[AsTwigFunction('company')]
    public function company(): Company
    {
        return $this->companies->get();
    }

    #[AsTwigFilter('mmss')]
    public function mmss(int $seconds): string
    {
        return \sprintf('%02d:%02d', intdiv(max(0, $seconds), 60), max(0, $seconds) % 60);
    }
}
