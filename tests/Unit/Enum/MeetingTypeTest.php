<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\MeetingType;
use App\Enum\SituationFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The meeting type guessed from a situation's key (French or English) and family, and its description.
 */
final class MeetingTypeTest extends TestCase
{
    /**
     * @return iterable<array{string, SituationFamily, MeetingType}>
     */
    public static function situations(): iterable
    {
        yield ['unhappy_client', SituationFamily::Client, MeetingType::Unhappy];
        yield ['client_mecontent', SituationFamily::Client, MeetingType::Unhappy];
        yield ['client_wants_to_cancel', SituationFamily::Client, MeetingType::Unhappy];
        yield ['key_developer_leaving', SituationFamily::Client, MeetingType::Unhappy];
        yield ['renewal_discount', SituationFamily::Client, MeetingType::Negotiation];
        yield ['competitor_cheaper', SituationFamily::Prospecting, MeetingType::Negotiation];
        yield ['burnt_by_offshore', SituationFamily::Prospecting, MeetingType::Objection];
        yield ['objection_price', SituationFamily::Prospecting, MeetingType::Objection];
        yield ['quote_follow_up', SituationFamily::Prospecting, MeetingType::Closing];
        yield ['relance_devis', SituationFamily::Prospecting, MeetingType::Closing];
        yield ['price_request', SituationFamily::Prospecting, MeetingType::Rushed];
        yield ['developers_by_monday', SituationFamily::Prospecting, MeetingType::Rushed];
        yield ['funding_round', SituationFamily::Prospecting, MeetingType::Rushed];
        yield ['big_deal', SituationFamily::Negotiation, MeetingType::Negotiation];
        yield ['signal_cold_call', SituationFamily::Prospecting, MeetingType::Discovery];
        yield ['gatekeeper', SituationFamily::Client, MeetingType::Discovery];
    }

    #[DataProvider('situations')]
    public function testOfSituation(string $key, SituationFamily $family, MeetingType $expected): void
    {
        self::assertSame($expected, MeetingType::ofSituation($key, $family));
    }

    public function testDescribe(): void
    {
        self::assertSame('an unhappy client', MeetingType::Unhappy->describe());
        foreach (MeetingType::cases() as $type) {
            self::assertNotSame('', $type->describe());
        }
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.meeting_type.unhappy_client', MeetingType::Unhappy->trans(new IdentityTranslator()));
    }
}
