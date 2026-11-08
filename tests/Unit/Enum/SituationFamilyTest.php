<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SituationFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Reading a situation family written by the model (French values included), and its translation key.
 */
final class SituationFamilyTest extends TestCase
{
    /**
     * @return iterable<array{mixed, SituationFamily}>
     */
    public static function values(): iterable
    {
        yield ['client', SituationFamily::Client];
        yield ['Relation client', SituationFamily::Client];
        yield ['customer', SituationFamily::Client];
        yield ['negotiation', SituationFamily::Negotiation];
        yield [' Négociation ', SituationFamily::Negotiation];
        yield ['negociation', SituationFamily::Negotiation];
        yield ['prospecting', SituationFamily::Prospecting];
        yield ['anything', SituationFamily::Prospecting];
        yield [null, SituationFamily::Prospecting];
    }

    #[DataProvider('values')]
    public function testFromModel(mixed $value, SituationFamily $expected): void
    {
        self::assertSame($expected, SituationFamily::fromModel($value));
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.situation_family.client', SituationFamily::Client->trans(new IdentityTranslator()));
    }
}
