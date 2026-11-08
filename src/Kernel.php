<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Practicall's kernel: the micro-kernel, with the application's time zone set on boot.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * The application's time zone, whatever PHP's own setting on the machine (APP_TIMEZONE to change it).
     */
    public function boot(): void
    {
        date_default_timezone_set(\is_string($_SERVER['APP_TIMEZONE'] ?? null) && '' !== $_SERVER['APP_TIMEZONE'] ? $_SERVER['APP_TIMEZONE'] : 'Europe/Paris');
        parent::boot();
    }

    /**
     * @return list<string> the allowed values of APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
