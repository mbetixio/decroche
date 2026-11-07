<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * What every template may need: the company, dates in its working language, and a short duration in minutes and
 * seconds.
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

    /**
     * A day, as written in the company's language ("14/11/2026", "November 14, 2026").
     */
    #[AsTwigFilter('day')]
    public function day(?\DateTimeInterface $date): string
    {
        return null !== $date ? $date->format($this->companies->get()->language->dateFormat()) : '—';
    }

    /**
     * A day and a time.
     */
    #[AsTwigFilter('moment')]
    public function moment(?\DateTimeInterface $date): string
    {
        return null !== $date ? $this->day($date).' · '.$date->format('H:i') : '—';
    }
}
