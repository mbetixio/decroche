<?php

declare(strict_types=1);

namespace App\Call\Speech;

use App\Ai\Data;

/**
 * A call in figures: how the seller talked, measured on the transcript (not on the audio).
 */
final readonly class SpeechFigures
{
    /**
     * @param array<string, int> $fillerWords the most frequent filler words, with their count
     */
    public function __construct(
        /** Whether the seller spoke into the microphone (rather than typed). */
        public bool $voice,
        /** The seller's share of the words said, in percent. */
        public int $talkShare,
        public int $sellerWords,
        public int $personaWords,
        public int $questions,
        public int $openQuestions,
        public int $fillers,
        public array $fillerWords,
        /** The seller's longest line, in words. */
        public int $longest,
        /** Words per minute, when spoken. */
        public ?int $wordsPerMinute,
        /** The average time the seller took to answer, in seconds. */
        public ?int $replyDelay,
    ) {
    }

    /**
     * @return array{voice: bool, talk_share: int, seller_words: int, persona_words: int, questions: int, open_questions: int, fillers: int, filler_words: array<string, int>, longest: int, wpm: ?int, reply_delay: ?int}
     */
    public function toArray(): array
    {
        return [
            'voice' => $this->voice, 'talk_share' => $this->talkShare, 'seller_words' => $this->sellerWords,
            'persona_words' => $this->personaWords, 'questions' => $this->questions, 'open_questions' => $this->openQuestions,
            'fillers' => $this->fillers, 'filler_words' => $this->fillerWords, 'longest' => $this->longest,
            'wpm' => $this->wordsPerMinute, 'reply_delay' => $this->replyDelay,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $int = static fn (string $k): int => \is_numeric($data[$k] ?? null) ? (int) $data[$k] : 0;
        $maybe = static fn (string $k): ?int => \is_numeric($data[$k] ?? null) ? (int) $data[$k] : null;

        $fillerWords = [];
        foreach (Data::map($data['filler_words'] ?? null) as $word => $count) {
            $fillerWords[(string) $word] = Data::int($count);
        }

        return new self(true === ($data['voice'] ?? false), $int('talk_share'), $int('seller_words'), $int('persona_words'), $int('questions'), $int('open_questions'), $int('fillers'), $fillerWords, $int('longest'), $maybe('wpm'), $maybe('reply_delay'));
    }

    /**
     * The figures in one sentence, for the coach.
     */
    public function describe(): string
    {
        return \sprintf('The call in figures: the seller had %d %% of the words (%d against %d), %d question(s) of which %d open, %d filler word(s)%s, a longest line of %d words%s%s.',
            $this->talkShare, $this->sellerWords, $this->personaWords, $this->questions, $this->openQuestions, $this->fillers,
            [] === $this->fillerWords ? '' : ' ('.implode(', ', array_keys($this->fillerWords)).')', $this->longest,
            null !== $this->wordsPerMinute ? ', a pace of '.$this->wordsPerMinute.' words per minute' : '',
            $this->voice ? '; the call was made by voice (the lines are transcriptions: ignore spelling and punctuation)' : '');
    }
}
