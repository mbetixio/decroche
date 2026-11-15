<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Enum\Language;
use App\Enum\Tone;
use App\Model\Situation;
use App\Tests\Unit\Fixtures;
use App\Voice\EdgeTtsSynthesizer;
use App\Voice\PocketTtsSynthesizer;
use App\Voice\VoiceCachePruner;
use App\Voice\VoiceCasting;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Casting the persona's voice: by language and gender (given or guessed from the first name), the Quebec accent,
 * always the same voice for the same persona, and the prosody of their tone; Pocket TTS's voices when its server runs.
 */
final class VoiceCastingTest extends TestCase
{
    use Fixtures;

    private static function voice(string $name, Language $language = Language::French, ?string $gender = null): string
    {
        return new VoiceCasting()->voice(self::scenario(persona: self::persona(gender: $gender, name: $name)), $language);
    }

    public function testFrenchVoicesByGivenGender(): void
    {
        self::assertSame('fr-FR-DeniseNeural', self::voice('Paul Martin', gender: 'f'));
        self::assertSame('fr-FR-HenriNeural', self::voice('Claire Dubois', gender: 'm'));
    }

    public function testEnglishVoicesByGivenGender(): void
    {
        self::assertContains(self::voice('Claire Dubois', Language::English, 'f'), VoiceCasting::VOICES['en-US']['f']);
        self::assertContains(self::voice('Claire Dubois', Language::English, 'm'), VoiceCasting::VOICES['en-US']['m']);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function firstNames(): iterable
    {
        yield ['Claire Dubois', 'fr-FR-DeniseNeural'];
        yield ['Julie Petit', 'fr-FR-DeniseNeural'];
        yield ['Sandrine Roux', 'fr-FR-DeniseNeural'];
        yield ['Isabelle Moreau', 'fr-FR-DeniseNeural'];
        yield ['Emma Leroy', 'fr-FR-DeniseNeural'];
        yield ['Thomas Bernard', 'fr-FR-HenriNeural'];
        yield ['Pierre Lambert', 'fr-FR-HenriNeural'];
        yield ['Stéphane Girard', 'fr-FR-HenriNeural'];
        yield ['Jean-Pierre Blanc', 'fr-FR-HenriNeural'];
        yield ['Luca Rossi', 'fr-FR-HenriNeural'];
    }

    #[DataProvider('firstNames')]
    public function testGenderGuessedFromTheFirstName(string $name, string $voice): void
    {
        self::assertSame($voice, self::voice($name));
    }

    public function testQuebecAccent(): void
    {
        $scenario = self::scenario(persona: self::persona(gender: 'm', name: 'Antoine Tremblay'));
        $scenario->setSituation(Situation::fromArray('quebec', ['label' => 'A client in Quebec', 'pitch' => 'A client from Montreal calls about a project.', 'objectives' => ['Listen']]));
        $casting = new VoiceCasting();

        self::assertContains($casting->voice($scenario, Language::French), VoiceCasting::VOICES['fr-CA']['m']);
        self::assertContains($casting->voice($scenario, Language::English), VoiceCasting::VOICES['en-US']['m']);
    }

    public function testTheSamePersonaKeepsTheSameVoice(): void
    {
        $voices = [];
        foreach (['Adam Smith', 'Brian Jones', 'Chris Lee', 'Dan Brown', 'Eric Wood', 'Frank Hill'] as $name) {
            $voice = self::voice($name, Language::English, 'm');
            self::assertSame($voice, self::voice($name, Language::English, 'm'));
            $voices[$voice] = true;
        }

        self::assertCount(2, $voices, 'the name spreads personas across the voices');
    }

    public function testProsodyFollowsTheTone(): void
    {
        $casting = new VoiceCasting();
        $angry = $casting->prosody(self::scenario(persona: self::persona(Tone::Angry)));
        $plain = $casting->prosody(self::scenario());

        self::assertSame([Tone::Angry->rate(), Tone::Angry->pitch()], [$angry->rate, $angry->pitch]);
        self::assertSame(['+12%', '+0Hz'], [$plain->rate, $plain->pitch]);
    }

    public function testPocketVoicesWhenItsServerRuns(): void
    {
        $casting = new VoiceCasting(self::pocket(), self::edge(false));
        $claire = self::scenario(persona: self::persona(gender: 'f', name: 'Claire Dubois'));
        $paul = self::scenario(persona: self::persona(gender: 'm', name: 'Paul Martin'));

        self::assertContains(substr($casting->voice($claire, Language::French), 7), VoiceCasting::POCKET['fr']['f']);
        self::assertContains(substr($casting->voice($paul, Language::French), 7), VoiceCasting::POCKET['fr']['m']);
        self::assertContains(substr($casting->voice($claire, Language::English), 7), VoiceCasting::POCKET['en']['f']);
        self::assertStringStartsWith('pocket:', $casting->voice($paul, Language::English));
        self::assertSame($casting->voice($paul, Language::English), $casting->voice($paul, Language::English));
        foreach (VoiceCasting::POCKET['fr'] as $voices) {
            foreach ($voices as $voice) {
                self::assertFileExists(\dirname(__DIR__, 3).'/public/voices/'.$voice.'.safetensors');
            }
        }
    }

    public function testTheQuebecAccentStaysWithEdgeWhenItCan(): void
    {
        $scenario = self::scenario(persona: self::persona(gender: 'm', name: 'Antoine Tremblay'));
        $scenario->setSituation(Situation::fromArray('quebec', ['label' => 'A client in Quebec', 'pitch' => 'A client from Montreal calls about a project.', 'objectives' => ['Listen']]));

        self::assertContains(new VoiceCasting(self::pocket(), self::edge(true))->voice($scenario, Language::French), VoiceCasting::VOICES['fr-CA']['m']);
        self::assertStringStartsWith('pocket:fr/m-', new VoiceCasting(self::pocket(), self::edge(false))->voice($scenario, Language::French));
    }

    private static function pocket(): PocketTtsSynthesizer
    {
        return new PocketTtsSynthesizer(new MockHttpClient([new MockResponse('{}')]), 'http://pocket:8100', 'http://app/voices', sys_get_temp_dir(), new VoiceCachePruner(sys_get_temp_dir()));
    }

    private static function edge(bool $installed): EdgeTtsSynthesizer
    {
        return new EdgeTtsSynthesizer($installed ? '/bin/sh' : '/nonexistent/edge-tts', sys_get_temp_dir(), new VoiceCachePruner(sys_get_temp_dir()));
    }
}
