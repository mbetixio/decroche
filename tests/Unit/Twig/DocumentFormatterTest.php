<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\DocumentFormatter;
use PHPUnit\Framework\TestCase;

/**
 * The seller's document as a CRM record: a header, "Label: value" fields, free notes, everything escaped.
 */
final class DocumentFormatterTest extends TestCase
{
    private const string SHEET = <<<'TXT'
        FICHE PROSPECT — THIBAULT MERCIER, SATELIS MOBILE
        Source : formulaire du site, 11/11/2026. Message : « Portail revendeurs à faire évoluer. »
        Secteur : eSIM et télécoms, PME 40 salariés, Lille.
        Aucune info budget, aucune info stack.
        Objection probable : « L'offshore, c'est moins cher, mais j'ai peur de la qualité. »
        - Vérifier l'existant
        TXT;

    public function testASheetBecomesARecord(): void
    {
        $html = new DocumentFormatter()->format(self::SHEET);

        self::assertStringContainsString('<span class="record-kind">Fiche prospect</span><strong class="record-subject">Thibault Mercier, Satelis Mobile</strong>', $html);
        self::assertStringContainsString('<dt>Source</dt><dd>formulaire du site, 11/11/2026. Message : « Portail revendeurs à faire évoluer. »</dd>', $html);
        self::assertStringContainsString('<dt>Objection probable</dt>', $html, 'A field after a note is still a field.');
        self::assertStringContainsString('<p>Aucune info budget, aucune info stack.</p>', $html);
        self::assertStringContainsString('<p class="record-bullet">Vérifier l&#039;existant</p>', $html);
    }

    public function testATextWithoutHeaderOrFields(): void
    {
        self::assertSame('<div class="record"><div class="record-notes"><p>Just a message.</p><p>Second line.</p></div></div>', new DocumentFormatter()->format("Just a message.\nSecond line."));
    }

    public function testEverythingIsEscaped(): void
    {
        $html = new DocumentFormatter()->format('Name: <script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNothing(): void
    {
        self::assertSame('', new DocumentFormatter()->format(null));
        self::assertSame('', new DocumentFormatter()->format("  \n "));
    }
}
