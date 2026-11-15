<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\SpokenText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lines made ready to be said: the numbers, prices, percentages, times and ordinals a persona writes, in words, and
 * the typography that made Pocket TTS stumble ("32,90 €" was heard as "by jar"), made plain.
 */
final class SpokenTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function lines(): iterable
    {
        yield 'a French price' => ['fr', 'Par contre 32,90 €, je payais 30 € chez eux.', 'Par contre trente-deux euros quatre-vingt-dix, je payais trente euros chez eux.'];
        yield 'one euro, round cents' => ['fr', '1 € ou 2,00 euros', 'un euro ou deux euros'];
        yield 'thousands and millions' => ['fr', 'Un budget de 50 k€, voire 2 M€, pour 40 000 clients.', 'Un budget de cinquante mille euros, voire deux millions d\'euros, pour quarante mille clients.'];
        yield 'grouped with decimals' => ['fr', '1 250,50 €', 'mille deux cent cinquante euros cinquante'];
        yield 'percentages and decimals' => ['fr', '15 % de remise, un taux de 2,5 ou 1,05', 'quinze pour cent de remise, un taux de deux virgule cinq ou un virgule zéro cinq'];
        yield 'French times' => ['fr', 'À 14h30, 9 h ou 1h00.', 'À quatorze heures trente, neuf heures ou une heure.'];
        yield 'French ordinals' => ['fr', 'Le 1er mars, la 1re fois, le 3e trimestre, la 2ème.', 'Le premier mars, la première fois, le troisième trimestre, la deuxième.'];
        yield 'typography' => ['fr', "Comment allez\u{2011}vous\u{00A0}? Ah, le chat\u{2026} non. Bon… Voilà...", 'Comment allez-vous ? Ah, le chat, non. Bon. Voilà.'];
        yield 'quotes and ampersand' => ['fr', 'Il a dit « oui » à R&D.', 'Il a dit " oui " à R et D.'];
        yield 'an English price' => ['en', 'It costs $1,250.50, or €1, about 30% more.', 'It costs one thousand two hundred fifty dollars fifty, or one euro, about thirty percent more.'];
        yield 'English times' => ['en', 'Call me at 2:30 pm, 11am or 9:00 a.m. tomorrow.', 'Call me at two thirty p m, eleven a m or nine a m tomorrow.'];
        yield 'English ordinals and numbers' => ['en', 'The 3rd week, 40,000 users, 1.5 million, 2 dollars.', 'The third week, forty thousand users, one point five million, two dollars.'];
        yield 'English scale' => ['en', 'A $2M budget.', 'A two million dollars budget.'];
    }

    #[DataProvider('lines')]
    public function testALineIsSaidInWords(string $language, string $written, string $said): void
    {
        self::assertSame($said, new SpokenText($language)->of($written));
    }
}
