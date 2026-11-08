<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Enum\CallDirection;
use App\Enum\SituationFamily;
use App\Model\Situation;
use PHPUnit\Framework\TestCase;

/**
 * A call situation: built from loose data (or refused when its essentials are missing), its key slug, its clean
 * string lists and its round trip.
 */
final class SituationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function data(array $override = []): array
    {
        return $override + [
            'label' => '  Unhappy client  ',
            'emoji' => '😠',
            'family' => 'Relation client',
            'direction' => 'entrant',
            'material' => ' The client file ',
            'pitch' => ' A client calls because the project is late again. ',
            'objectives' => ['Listen first', 'Rephrase the problem', 'Agree on a plan', 'A fourth one'],
        ];
    }

    public function testFromValidArray(): void
    {
        $situation = Situation::fromArray('unhappy_client', self::data());

        self::assertNotNull($situation);
        self::assertSame('unhappy_client', $situation->key);
        self::assertSame('Unhappy client', $situation->label);
        self::assertSame('😠', $situation->emoji);
        self::assertSame(SituationFamily::Client, $situation->family);
        self::assertSame(CallDirection::Incoming, $situation->direction);
        self::assertTrue($situation->isIncoming());
        self::assertSame('The client file', $situation->material);
        self::assertSame('A client calls because the project is late again.', $situation->pitch);
        self::assertSame(['Listen first', 'Rephrase the problem', 'Agree on a plan'], $situation->objectives);
    }

    public function testDefaults(): void
    {
        $situation = Situation::fromArray('x', ['label' => 'Cold call', 'pitch' => str_repeat('a', 20), 'objectives' => 'Book a meeting']);

        self::assertNotNull($situation);
        self::assertSame('📞', $situation->emoji);
        self::assertSame(SituationFamily::Prospecting, $situation->family);
        self::assertSame(CallDirection::Outgoing, $situation->direction);
        self::assertFalse($situation->isIncoming());
        self::assertSame('', $situation->material);
        self::assertSame(['Book a meeting'], $situation->objectives);
    }

    public function testKeyComesFromTheLabelWhenEmpty(): void
    {
        self::assertSame('unhappy_client', Situation::fromArray('', self::data())?->key);
        self::assertSame('some_key', Situation::fromArray('Some Key!', self::data())?->key);
    }

    public function testMissingEssentialsGiveNull(): void
    {
        self::assertNull(Situation::fromArray('k', self::data(['label' => '  '])));
        self::assertNull(Situation::fromArray('k', self::data(['pitch' => 'Too short.'])));
        self::assertNull(Situation::fromArray('k', self::data(['objectives' => []])));
        self::assertNull(Situation::fromArray('k', self::data(['objectives' => ['', ' - ']])));
        self::assertNull(Situation::fromArray('k', []));
        self::assertNull(Situation::fromArray('k', self::data(['label' => ['not', 'a', 'string']])));
    }

    public function testLabelAndEmojiAreCut(): void
    {
        $situation = Situation::fromArray('k', self::data(['label' => str_repeat('é', 100), 'emoji' => '😠😠😠😠😠😠']));

        self::assertSame(80, mb_strlen((string) $situation?->label));
        self::assertSame(4, mb_strlen((string) $situation?->emoji));
    }

    public function testRoundTrip(): void
    {
        $situation = Situation::fromArray('unhappy_client', self::data());
        self::assertNotNull($situation);
        $data = $situation->toArray();

        self::assertSame('client', $data['family']);
        self::assertSame('incoming', $data['direction']);
        self::assertEquals($situation, Situation::fromArray($data['key'], $data));
    }

    public function testSlugOfAccentedLabel(): void
    {
        $slug = Situation::slug('Client mécontent');
        self::assertSame('client_mecontent', $slug);
    }

    public function testSlug(): void
    {
        self::assertSame('price_request_on_the_website', Situation::slug('Price request — on the website!'));
        self::assertSame('situation', Situation::slug('😀 !!'));
        self::assertSame('situation', Situation::slug(''));
        self::assertLessThanOrEqual(28, \strlen(Situation::slug(str_repeat('abc ', 30))));
        self::assertMatchesRegularExpression('/^[a-z0-9_]+$/', Situation::slug('Ça coûte 1 000 € HT ?'));
    }

    public function testStringsFromList(): void
    {
        self::assertSame(['one', 'two', '3'], Situation::strings([' one ', '- two', 'one', '', ['nested'], 3, null, '•  ']));
    }

    public function testStringsFromText(): void
    {
        self::assertSame(['first', 'second', 'third'], Situation::strings("- first\r\n• second\n\n third "));
    }

    public function testStringsMax(): void
    {
        self::assertSame(['a', 'b'], Situation::strings(['a', 'b', 'c'], 2));
        self::assertSame([], Situation::strings(42));
        self::assertSame([], Situation::strings(null));
    }
}
