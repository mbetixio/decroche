<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Enum\Language;
use App\Tests\Unit\Fixtures;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

/**
 * The Twig helpers: the company, a duration in minutes and seconds, a day and a moment in the company's language. The
 * company repository is final: it is a real one over stubbed Doctrine plumbing.
 */
final class AppExtensionTest extends TestCase
{
    use Fixtures;

    private static function extension(Language $language = Language::French): AppExtension
    {
        return new AppExtension(self::companies(self::company($language)));
    }

    public function testCompany(): void
    {
        self::assertSame('Etix', self::extension()->company()->name);
    }

    public function testMmss(): void
    {
        $extension = self::extension();

        self::assertSame('00:00', $extension->mmss(0));
        self::assertSame('00:09', $extension->mmss(9));
        self::assertSame('01:05', $extension->mmss(65));
        self::assertSame('12:00', $extension->mmss(720));
        self::assertSame('125:00', $extension->mmss(7500));
        self::assertSame('00:00', $extension->mmss(-30));
    }

    public function testDay(): void
    {
        $date = new \DateTimeImmutable('2026-11-14 09:05:00');

        self::assertSame('14/11/2026', self::extension(Language::French)->day($date));
        self::assertSame('November 14, 2026', self::extension(Language::English)->day($date));
        self::assertSame('—', self::extension()->day(null));
    }

    public function testMoment(): void
    {
        $date = new \DateTime('2026-11-14 09:05:00');

        self::assertSame('14/11/2026 · 09:05', self::extension(Language::French)->moment($date));
        self::assertSame('November 14, 2026 · 09:05', self::extension(Language::English)->moment($date));
        self::assertSame('—', self::extension()->moment(null));
    }
}
