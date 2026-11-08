<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Tone;
use PHPUnit\Framework\TestCase;

/**
 * A persona's tone: the tones a situation may draw, and the rate and pitch of the voice.
 */
final class ToneTest extends TestCase
{
    public function testForKnownSituation(): void
    {
        self::assertSame([Tone::Angry, Tone::Angry, Tone::LetDown, Tone::Stressed], Tone::forSituation('unhappy_client'));
        self::assertSame([Tone::Stressed, Tone::Busy], Tone::forSituation('developers_by_monday'));
        self::assertSame(Tone::forSituation('gatekeeper'), Tone::forSituation('barrage'));
    }

    public function testForUnknownTenseSituation(): void
    {
        $tense = [Tone::Angry, Tone::LetDown, Tone::Stressed, Tone::Worried];
        self::assertSame($tense, Tone::forSituation('delivery_delay'));
        self::assertSame($tense, Tone::forSituation('server_outage'));
        self::assertSame($tense, Tone::forSituation('plainte_facture'));
    }

    public function testForEverydaySituationIsMostlyPleasant(): void
    {
        $tones = Tone::forSituation('price_request');

        self::assertCount(11, $tones);
        self::assertContains(Tone::Warm, $tones);
        self::assertNotContains(Tone::Angry, $tones);
        self::assertNotContains(Tone::LetDown, $tones);
    }

    public function testEveryToneHasAValidVoiceAndDescription(): void
    {
        foreach (Tone::cases() as $tone) {
            self::assertMatchesRegularExpression('/^[+-]\d{1,2}%$/', $tone->rate());
            self::assertMatchesRegularExpression('/^[+-]\d{1,2}Hz$/', $tone->pitch());
            self::assertNotSame('', $tone->describe());
        }
    }

    public function testSomeVoices(): void
    {
        self::assertSame('+20%', Tone::Angry->rate());
        self::assertSame('+4Hz', Tone::Angry->pitch());
        self::assertSame('+2%', Tone::LetDown->rate());
        self::assertSame('-4Hz', Tone::LetDown->pitch());
    }
}
