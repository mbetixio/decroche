<?php

declare(strict_types=1);

namespace App\Call\Speech;

use App\Entity\Call;
use App\Enum\Language;
use App\Enum\Speaker;
use App\Model\Line;

/**
 * Measures how the seller talked, from the transcript and in the working language: talk share, questions (open ones), filler words, longest line,
 * speech rate when spoken, reply delay.
 */
final readonly class SpeechAnalyzer
{
    /** Filler words that weaken a pitch, by language (speech recognition keeps the "euh" and "um" when they are said). */
    public const array FILLERS = [
        'fr' => ['euh', 'heu', 'bah', 'ben', 'du coup', 'en fait', 'voilà', 'genre', 'en gros', 'quoi', 'enfin bref', 'vous voyez', 'tu vois', 'franchement', 'honnêtement'],
        'en' => ['um', 'uh', 'erm', 'like', 'you know', 'i mean', 'basically', 'actually', 'literally', 'sort of', 'kind of', 'to be honest', 'honestly', 'right'],
    ];

    /** Words that open a question, by language, so that an unpunctuated spoken question still counts (and counts as open). */
    private const array ASKING = [
        'fr' => ['qu\'est-ce', 'qu est-ce', 'comment', 'pourquoi', 'quel', 'quelle', 'quels', 'quelles', 'combien', 'est-ce que', 'qui', 'où', 'quand', 'lequel', 'laquelle', 'racontez', 'dites-moi', 'expliquez'],
        'en' => ['what', 'how', 'why', 'which', 'who', 'where', 'when', 'tell me', 'walk me through', 'could you describe', 'can you explain'],
    ];

    public function analyze(Call $call, Language $language = Language::French): SpeechFigures
    {
        $asking = self::ASKING[$language->value];
        $fillerWords = self::FILLERS[$language->value];
        $lines = $call->lines();
        $seller = array_values(array_filter($lines, static fn (Line $l): bool => $l->isSeller()));
        $sellerWords = array_sum(array_map(static fn (Line $l): int => self::words($l->text), $seller));
        $personaWords = array_sum(array_map(static fn (Line $l): int => self::words($l->text), array_filter($lines, static fn (Line $l): bool => !$l->isSeller())));

        $questions = $open = 0;
        $fillers = [];
        foreach ($seller as $line) {
            $text = ' '.mb_strtolower($line->text).' ';
            foreach (preg_split('/(?<=[.?!])\s+/u', trim($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
                // Whole words only: "however" is not "how", "quelque chose" is not "quel".
                $asks = array_filter($asking, static fn (string $w): bool => 1 === preg_match('/(?<![\p{L}\'])'.preg_quote($w, '/').'(?![\p{L}])/u', $sentence));
                if (str_contains($sentence, '?') || [] !== $asks) {
                    ++$questions;
                    $open += [] !== $asks ? 1 : 0;
                }
            }
            foreach ($fillerWords as $filler) {
                if (($n = (int) preg_match_all('/(?<![\p{L}])'.preg_quote($filler, '/').'(?![\p{L}])/u', $text)) > 0) {
                    $fillers[$filler] = ($fillers[$filler] ?? 0) + $n;
                }
            }
        }
        arsort($fillers);

        // The speech rate, when spoken: words said over time spent talking.
        $voiced = array_filter($seller, static fn (Line $l): bool => ($l->voiceMs ?? 0) > 500);
        $ms = array_sum(array_map(static fn (Line $l): int => (int) $l->voiceMs, $voiced));
        $wpm = $ms > 0 ? (int) round(array_sum(array_map(static fn (Line $l): int => self::words($l->text), $voiced)) / ($ms / 60_000)) : null;

        // The reply delay: between the end of the client's line and the seller's next one.
        $delays = [];
        foreach ($lines as $i => $line) {
            $previous = $lines[$i - 1] ?? null;
            if ($line->isSeller() && Speaker::Persona === $previous?->speaker) {
                $delays[] = max(0, $line->at->getTimestamp() - $previous->at->getTimestamp() - (int) round(($line->voiceMs ?? 0) / 1000));
            }
        }

        return new SpeechFigures(
            voice: [] !== $voiced,
            talkShare: $sellerWords + $personaWords > 0 ? (int) round($sellerWords / ($sellerWords + $personaWords) * 100) : 0,
            sellerWords: $sellerWords,
            personaWords: $personaWords,
            questions: $questions,
            openQuestions: $open,
            fillers: array_sum($fillers),
            fillerWords: \array_slice($fillers, 0, 5, true),
            longest: [] === $seller ? 0 : max(array_map(static fn (Line $l): int => self::words($l->text), $seller)),
            wordsPerMinute: $wpm,
            replyDelay: [] === $delays ? null : (int) round(array_sum($delays) / \count($delays)),
        );
    }

    private static function words(string $text): int
    {
        return \count(preg_split('/[\s\'’-]+/u', trim($text), -1, \PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
