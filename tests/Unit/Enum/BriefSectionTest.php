<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\BriefSection;
use App\Enum\CallStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The brief sections: their order, their descriptions for the model, and their translation key.
 */
final class BriefSectionTest extends TestCase
{
    public function testOrder(): void
    {
        self::assertSame(['budget', 'pain', 'decision', 'objections', 'yes', 'secret'], array_map(static fn (BriefSection $s): string => $s->value, BriefSection::cases()));
    }

    public function testDescriptions(): void
    {
        foreach (BriefSection::cases() as $section) {
            self::assertNotSame('', $section->describe());
            self::assertNotSame('', $section->prompt());
        }
        self::assertSame('Real budget', BriefSection::Budget->describe());
    }

    public function testTranslationKeys(): void
    {
        $translator = new IdentityTranslator();

        self::assertSame('enum.brief_section.budget', BriefSection::Budget->trans($translator));
        self::assertSame('enum.call_status.debriefing', CallStatus::Debriefing->trans($translator));
    }
}
