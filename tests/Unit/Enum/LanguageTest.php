<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Language;
use PHPUnit\Framework\TestCase;

/**
 * The working language's names, speech recognition locale and date format.
 */
final class LanguageTest extends TestCase
{
    public function testFrench(): void
    {
        self::assertSame('Français', Language::French->nativeName());
        self::assertSame('French', Language::French->englishName());
        self::assertSame('fr-FR', Language::French->speechLocale());
        self::assertSame('14/11/2026', new \DateTimeImmutable('2026-11-14')->format(Language::French->dateFormat()));
    }

    public function testEnglish(): void
    {
        self::assertSame('English', Language::English->nativeName());
        self::assertSame('English', Language::English->englishName());
        self::assertSame('en-US', Language::English->speechLocale());
        self::assertSame('November 14, 2026', new \DateTimeImmutable('2026-11-14')->format(Language::English->dateFormat()));
    }

    public function testValues(): void
    {
        self::assertSame(Language::French, Language::from('fr'));
        self::assertSame(Language::English, Language::from('en'));
        self::assertNull(Language::tryFrom('de'));
    }
}
