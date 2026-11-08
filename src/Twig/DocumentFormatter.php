<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Lays out the document a seller has in front of them (a prospect sheet, a client file, a form message, as the model
 * writes it in plain text) like a CRM record: a header (what it is, and about whom), "Label: value" lines as a grid of
 * fields, and the rest as notes. Everything is escaped; only the layout is HTML.
 */
final class DocumentFormatter
{
    /** A "Label: value" line: a short label, a colon (with or without a space before it, as in French), a value. */
    private const string FIELD = '/^(?<label>[^:«"\n]{2,40}?)\s*:\s+(?<value>.+)$/u';

    #[AsTwigFilter('document', isSafe: ['html'])]
    public function format(?string $text): string
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", (string) $text)), static fn (string $l): bool => '' !== $l));
        if ([] === $lines) {
            return '';
        }

        $html = '<div class="record">';
        // The header: a short first line that reads as a title ("PROSPECT SHEET — JANE DOE, ACME"), not a sentence.
        if (self::isTitle($lines[0])) {
            $html .= $this->header(array_shift($lines));
        }
        $fields = $notes = [];
        foreach ($lines as $line) {
            if (1 === preg_match(self::FIELD, $line, $m)) {
                $fields[] = '<div class="record-field"><dt>'.self::e(self::sentence($m['label'])).'</dt><dd>'.self::e($m['value']).'</dd></div>';
            } else {
                $notes[] = $line;
            }
        }
        if ([] !== $fields) {
            $html .= '<dl class="record-fields">'.implode('', $fields).'</dl>';
        }
        if ([] !== $notes) {
            $html .= '<div class="record-notes">'.implode('', array_map(static fn (string $n): string => str_starts_with($n, '- ') || str_starts_with($n, '• ')
                ? '<p class="record-bullet">'.self::e(mb_substr($n, 2)).'</p>'
                : '<p>'.self::e($n).'</p>', $notes)).'</div>';
        }

        return $html.'</div>';
    }

    /**
     * "PROSPECT SHEET — JANE DOE, ACME": the kind of document above, the subject below.
     */
    private function header(string $line): string
    {
        $parts = preg_split('/\s+[—–-]\s+/u', $line, 2) ?: [$line];
        $kind = 2 === \count($parts) ? self::sentence($parts[0]) : null;
        $subject = self::calm($parts[\count($parts) - 1]);

        return '<header class="record-head">'.(null !== $kind ? '<span class="record-kind">'.self::e($kind).'</span>' : '').'<strong class="record-subject">'.self::e($subject).'</strong></header>';
    }

    private static function isTitle(string $line): bool
    {
        return mb_strlen($line) <= 90 && 1 !== preg_match(self::FIELD, $line)
            && (mb_strtoupper($line) === $line || 1 === preg_match('/\s[—–]\s/u', $line));
    }

    /**
     * A label or kind written in capitals becomes a sentence ("PROSPECT SHEET" → "Prospect sheet").
     */
    private static function sentence(string $text): string
    {
        if (mb_strtoupper($text) !== $text) {
            return $text;
        }
        $lower = mb_strtolower($text);

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }

    /**
     * A subject written in capitals gets its capitals back where they belong ("JANE DOE, ACME" → "Jane Doe, Acme").
     */
    private static function calm(string $text): string
    {
        return mb_strtoupper($text) === $text ? mb_convert_case(mb_strtolower($text), \MB_CASE_TITLE) : $text;
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
