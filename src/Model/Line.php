<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\Speaker;

/**
 * One line of a call, as said (or typed) and when.
 */
final readonly class Line
{
    public function __construct(
        public Speaker $speaker,
        public string $text,
        public \DateTimeImmutable $at,
        /** Spoken into the microphone: how long it took, in milliseconds (for the speech rate). */
        public ?int $voiceMs = null,
    ) {
    }

    /**
     * @param array{role: string, content: string, at: string, voice?: array{ms: int}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Speaker::from($data['role']), $data['content'], new \DateTimeImmutable($data['at']), $data['voice']['ms'] ?? null);
    }

    /**
     * @return array{role: string, content: string, at: string, voice?: array{ms: int}}
     */
    public function toArray(): array
    {
        return ['role' => $this->speaker->value, 'content' => $this->text, 'at' => $this->at->format(\DATE_ATOM)]
            + (null !== $this->voiceMs ? ['voice' => ['ms' => $this->voiceMs]] : []);
    }

    public function isSeller(): bool
    {
        return Speaker::Seller === $this->speaker;
    }
}
