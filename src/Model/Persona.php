<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\Tone;

/**
 * The client simulated on the other end of the line.
 */
final readonly class Persona
{
    public function __construct(
        public string $name,
        public string $role,
        public string $company,
        public string $personality,
        /** "f" or "m": picks the voice. Guessed from the first name when unknown. */
        public ?string $gender = null,
        public ?Tone $tone = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $text = static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '';
        $gender = mb_strtolower($text($data['gender'] ?? ''));

        return new self(
            name: $text($data['name'] ?? ''),
            role: $text($data['role'] ?? ''),
            company: $text($data['company'] ?? ''),
            personality: $text($data['personality'] ?? ''),
            gender: \in_array($gender, ['f', 'm'], true) ? $gender : null,
            tone: Tone::tryFrom($text($data['tone'] ?? '')),
        );
    }

    /**
     * @return array{name: string, role: string, company: string, personality: string, gender?: string, tone?: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'role' => $this->role, 'company' => $this->company, 'personality' => $this->personality]
            + (null !== $this->gender ? ['gender' => $this->gender] : [])
            + (null !== $this->tone ? ['tone' => $this->tone->value] : []);
    }

    public function withTone(Tone $tone): self
    {
        return new self($this->name, $this->role, $this->company, $this->personality, $this->gender, $tone);
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    public function initials(): string
    {
        $words = preg_split('/[\s-]+/', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $w): string => mb_substr($w, 0, 1), \array_slice($words, 0, 2))));
    }
}
