<?php

declare(strict_types=1);

namespace App\Model;

use function Symfony\Component\String\u;
use App\Enum\CallDirection;
use App\Enum\SituationFamily;

/**
 * A kind of call the company's sellers run into ("a price request from the website", "an unhappy client"…). Each
 * surprise call draws one, dresses it up at random, and the model writes the rest.
 */
final readonly class Situation
{
    /**
     * @param list<string> $objectives three concrete, checkable objectives for the call
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $emoji,
        public SituationFamily $family,
        public CallDirection $direction,
        /** What the seller has in front of them before the call ("the form message", "the client file"…), if anything. */
        public string $material,
        /** Who is on the line, what they want, what is stuck, what is hidden. */
        public string $pitch,
        public array $objectives,
    ) {
    }

    /**
     * A situation from stored or generated data; null when the essentials are missing.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(string $key, array $data): ?self
    {
        $text = static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '';
        $objectives = \array_slice(self::strings($data['objectives'] ?? []), 0, 3);
        $label = mb_substr($text($data['label'] ?? ''), 0, 80);
        $pitch = $text($data['pitch'] ?? '');
        if ('' === $label || mb_strlen($pitch) < 20 || [] === $objectives) {
            return null;
        }

        return new self(
            key: self::slug('' !== $key ? $key : $label),
            label: $label,
            emoji: mb_substr($text($data['emoji'] ?? '') ?: '📞', 0, 4),
            family: SituationFamily::fromModel($data['family'] ?? ''),
            direction: CallDirection::fromModel($data['direction'] ?? ''),
            material: $text($data['material'] ?? ''),
            pitch: $pitch,
            objectives: $objectives,
        );
    }

    /**
     * @return array{key: string, label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'emoji' => $this->emoji,
            'family' => $this->family->value,
            'direction' => $this->direction->value,
            'material' => $this->material,
            'pitch' => $this->pitch,
            'objectives' => $this->objectives,
        ];
    }

    public function isIncoming(): bool
    {
        return CallDirection::Incoming === $this->direction;
    }

    /**
     * A key for a label: lowercase ASCII words joined by underscores, 28 characters at most.
     */
    public static function slug(string $label): string
    {
        // Symfony's transliteration rather than iconv, whose result depends on the system ("é" gives "'e" on macOS).
        $ascii = u($label)->ascii()->lower()->toString();
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');

        return substr('' !== $slug ? $slug : 'situation', 0, 28);
    }

    /**
     * Clean strings from a list (or from text, one per line).
     *
     * @return list<string>
     */
    public static function strings(mixed $list, int $max = 20): array
    {
        if (\is_string($list)) {
            $list = preg_split('/\R/', $list) ?: [];
        }
        $clean = array_map(static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v, " \t-•") : '', \is_array($list) ? $list : []);

        return \array_slice(array_values(array_unique(array_filter($clean, static fn (string $v): bool => '' !== $v))), 0, $max);
    }
}
