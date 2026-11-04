<?php

namespace App\Training;

use App\Entity\Training;

/**
 * L'appel en chiffres : qui a parlé, combien, à quelle vitesse, avec combien de questions et de tics de langage. Le
 * débit n'existe qu'en mode vocal (la durée de parole vient du micro).
 */
final class Speech
{
    /** Les tics de langage qui affaiblissent un discours (la reconnaissance vocale garde les « euh » quand on les dit). */
    public const FILLERS = ['euh', 'heu', 'bah', 'ben', 'du coup', 'en fait', 'voilà', 'genre', 'en gros', 'quoi', 'enfin bref', 'vous voyez', 'tu vois', 'franchement', 'honnêtement'];

    private const ASKING = ['qu\'est-ce', 'qu est-ce', 'comment', 'pourquoi', 'quel', 'quelle', 'quels', 'quelles', 'combien', 'est-ce que', 'qui ', 'où ', 'quand', 'lequel', 'laquelle', 'racontez', 'dites-moi', 'expliquez'];

    /**
     * @return array{voice: bool, talk_share: int, seller_words: int, persona_words: int, questions: int, open_questions: int, fillers: int, filler_words: array<string, int>, longest: int, wpm: ?int, reply_delay: ?int}
     */
    public static function analyze(Training $training): array
    {
        $seller = array_values(array_filter($training->messages, static fn (array $m): bool => Training::SELLER === $m['role']));
        $words = static fn (string $t): int => \count(preg_split('/[\s\'’-]+/u', trim($t), -1, \PREG_SPLIT_NO_EMPTY) ?: []);
        $sellerWords = array_sum(array_map(static fn (array $m): int => $words($m['content']), $seller));
        $personaWords = array_sum(array_map(static fn (array $m): int => $words($m['content']), array_filter($training->messages, static fn (array $m): bool => Training::PERSONA === $m['role'])));

        $questions = $open = 0;
        $fillers = [];
        foreach ($seller as $m) {
            $text = ' '.mb_strtolower($m['content']).' ';
            // Une phrase interrogative : un point d'interrogation, ou (à l'oral, sans ponctuation) un mot interrogatif.
            $sentences = preg_split('/(?<=[.?!])\s+/u', trim($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($sentences as $sentence) {
                $asks = array_filter(self::ASKING, static fn (string $w): bool => str_contains(' '.$sentence, ' '.$w) || str_starts_with($sentence, trim($w)));
                if (str_contains($sentence, '?') || [] !== $asks) {
                    ++$questions;
                    if ([] !== $asks) {
                        ++$open;
                    }
                }
            }
            foreach (self::FILLERS as $filler) {
                $n = preg_match_all('/(?<![\p{L}])'.preg_quote($filler, '/').'(?![\p{L}])/u', $text);
                if ($n > 0) {
                    $fillers[$filler] = ($fillers[$filler] ?? 0) + (int) $n;
                }
            }
        }
        arsort($fillers);

        // Le débit, en mode vocal : les mots dits rapportés au temps passé à parler.
        $voiced = array_filter($seller, static fn (array $m): bool => isset($m['voice']) && $m['voice']['ms'] > 500);
        $ms = array_sum(array_map(static fn (array $m): int => $m['voice']['ms'], $voiced));
        $wpm = $ms > 0 ? (int) round(array_sum(array_map(static fn (array $m): int => $words($m['content']), $voiced)) / ($ms / 60000)) : null;

        // Le délai de réponse : entre la fin d'une réplique du client et la suivante du commercial.
        $delays = [];
        foreach ($training->messages as $i => $m) {
            $prev = $training->messages[$i - 1] ?? null;
            if ($i > 0 && Training::SELLER === $m['role'] && null !== $prev && Training::PERSONA === $prev['role']) {
                $delays[] = max(0, strtotime($m['at']) - strtotime($prev['at']) - (int) round(($m['voice']['ms'] ?? 0) / 1000));
            }
        }

        return [
            'voice' => [] !== $voiced,
            'talk_share' => $sellerWords + $personaWords > 0 ? (int) round($sellerWords / ($sellerWords + $personaWords) * 100) : 0,
            'seller_words' => $sellerWords,
            'persona_words' => $personaWords,
            'questions' => $questions,
            'open_questions' => $open,
            'fillers' => array_sum($fillers),
            'filler_words' => \array_slice($fillers, 0, 5, true),
            'longest' => [] === $seller ? 0 : max(array_map(static fn (array $m): int => $words($m['content']), $seller)),
            'wpm' => $wpm,
            'reply_delay' => [] === $delays ? null : (int) round(array_sum($delays) / \count($delays)),
        ];
    }
}
