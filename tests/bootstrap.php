<?php

declare(strict_types=1);

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Every test runs at the same moment, a Monday morning: no test depends on the day or the hour it runs at. The app's
// clock service and Symfony\Component\Clock\now() both read this one.
Clock::set(new MockClock('2026-11-16 10:00:00', 'Europe/Paris'));
