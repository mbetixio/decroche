<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Enum\Language;
use App\Model\Criterion;
use PHPUnit\Framework\TestCase;

/**
 * Sparring's default scorecard in each working language, and a criterion's array form.
 */
final class CriterionTest extends TestCase
{
    private const array KEYS = ['discovery', 'questions', 'listening', 'objections', 'value', 'closing'];

    public function testFrenchDefaults(): void
    {
        $defaults = Criterion::defaults(Language::French);

        self::assertSame(self::KEYS, array_keys($defaults));
        self::assertSame('Découverte du besoin', $defaults['discovery']->label);
        self::assertEquals($defaults, Criterion::defaults());
    }

    public function testEnglishDefaults(): void
    {
        $defaults = Criterion::defaults(Language::English);

        self::assertSame(self::KEYS, array_keys($defaults));
        self::assertSame('Needs discovery', $defaults['discovery']->label);
        self::assertSame('Closing / next step', $defaults['closing']->label);
    }

    public function testEveryDefaultIsKeyedByItsKeyAndHasAHint(): void
    {
        foreach (Language::cases() as $language) {
            foreach (Criterion::defaults($language) as $key => $criterion) {
                self::assertSame($key, $criterion->key);
                self::assertNotSame('', $criterion->hint);
            }
        }
    }

    public function testToArray(): void
    {
        self::assertSame(['label' => 'Rapport', 'hint' => 'builds trust'], new Criterion('rapport', 'Rapport', 'builds trust')->toArray());
        self::assertSame(['label' => 'Rapport', 'hint' => ''], new Criterion('rapport', 'Rapport')->toArray());
    }
}
