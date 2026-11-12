<?php

declare(strict_types=1);

namespace App\Model;

use App\Ai\Data;

/**
 * What a persona has at hand during a call, whatever their business: their calendar and a few sources (support
 * tickets, stock levels, suppliers, orders, e-mails…), each a list of records with free fields. Read from the JSON the
 * scenario writer produced; a scenario without one gives an empty workspace and a free calendar.
 */
final readonly class Workspace
{
    /** Office hours, for the free slots of a day. */
    private const int OPENS = 9;
    private const int CLOSES = 18;

    /**
     * @param list<array{when: string, minutes: int, what: string}>                                                                                                                        $calendar
     * @param array<string, array{label: string, description: string, entries: list<array{title: string, when: string, details: list<array{name: string, value: string}>, note: string}>}> $sources  by key
     */
    private function __construct(
        private array $calendar,
        private array $sources,
    ) {
    }

    /**
     * @param array<mixed>|null $data as the writer wrote it (see App\Ai\Output\Workspace)
     */
    public static function fromArray(?array $data): self
    {
        $calendar = [];
        foreach (Data::rows($data['calendar'] ?? null) as $slot) {
            $when = Data::string($slot['when'] ?? '');
            $moment = self::moment($when);
            // A slot the writer put on a weekend is left out: nobody is booked then, whatever the model wrote.
            if (null !== $moment && (int) $moment->format('N') < 6) {
                $calendar[] = ['when' => $when, 'minutes' => max(15, min(480, Data::int($slot['minutes'] ?? 60, 60))), 'what' => Data::string($slot['what'] ?? '')];
            }
        }
        $sources = [];
        foreach (Data::rows($data['sources'] ?? null) as $source) {
            $key = (string) preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(Data::string($source['key'] ?? '')));
            if ('' === trim($key, '_')) {
                continue;
            }
            $sources[$key] = [
                'label' => Data::string($source['label'] ?? '') ?: $key,
                'description' => Data::string($source['description'] ?? ''),
                'entries' => array_map(static fn (array $e): array => [
                    'title' => Data::string($e['title'] ?? ''),
                    'when' => Data::string($e['when'] ?? ''),
                    'details' => array_map(static fn (array $d): array => ['name' => Data::string($d['name'] ?? ''), 'value' => Data::string($d['value'] ?? '')], Data::rows($e['details'] ?? null)),
                    'note' => Data::string($e['note'] ?? ''),
                ], Data::rows($source['entries'] ?? null)),
            ];
        }

        return new self($calendar, $sources);
    }

    /**
     * @return array{calendar: list<array{when: string, minutes: int, what: string}>, sources: list<array{key: string, label: string, description: string, entries: list<array{title: string, when: string, details: list<array{name: string, value: string}>, note: string}>}>}
     */
    public function toArray(): array
    {
        return ['calendar' => $this->calendar, 'sources' => array_map(static fn (string $key, array $s): array => ['key' => $key] + $s, array_keys($this->sources), $this->sources)];
    }

    public function isEmpty(): bool
    {
        return [] === $this->calendar && [] === $this->sources;
    }

    /**
     * The sources, by key, with what they hold.
     *
     * @return array<string, array{label: string, description: string, entries: list<array{title: string, when: string, details: list<array{name: string, value: string}>, note: string}>}>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * The records of a source that match the query (every word of it, in any field), or all of them for an empty
     * query; the best matches first, at most five.
     *
     * @return list<array{title: string, when: string, details: list<array{name: string, value: string}>, note: string}>
     */
    public function search(string $source, string $query = ''): array
    {
        $entries = $this->sources[$source]['entries'] ?? [];
        $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [], static fn (string $w): bool => mb_strlen($w) > 1);
        if ([] === $words) {
            return \array_slice($entries, 0, 5);
        }
        $scored = [];
        foreach ($entries as $entry) {
            $text = mb_strtolower($entry['title'].' '.$entry['when'].' '.$entry['note'].' '.implode(' ', array_map(static fn (array $d): string => $d['name'].' '.$d['value'], $entry['details'])));
            $score = \count(array_filter($words, static fn (string $w): bool => str_contains($text, $w)));
            if ($score > 0) {
                $scored[] = [$score, $entry];
            }
        }
        usort($scored, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return array_map(static fn (array $s): array => $s[1], \array_slice($scored, 0, 5));
    }

    /**
     * The busy slots of a day, in order.
     *
     * @return list<array{when: string, minutes: int, what: string}>
     */
    public function busy(\DateTimeImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        $slots = array_values(array_filter($this->calendar, static fn (array $s): bool => str_starts_with($s['when'], $date)));
        usort($slots, static fn (array $a, array $b): int => strcmp($a['when'], $b['when']));

        return $slots;
    }

    /**
     * The free hours of a day, within office hours, around its busy slots (none on a weekend).
     *
     * @return list<string> as HH:MM
     */
    public function free(\DateTimeImmutable $day): array
    {
        if ((int) $day->format('N') >= 6) {
            return [];
        }
        $free = [];
        for ($hour = self::OPENS; $hour < self::CLOSES; ++$hour) {
            $start = $day->setTime($hour, 0);
            if (!$this->overlaps($start, 60)) {
                $free[] = $start->format('H:i');
            }
        }

        return $free;
    }

    /**
     * Whether a meeting at that moment would fall on a busy slot.
     */
    public function overlaps(\DateTimeImmutable $start, int $minutes): bool
    {
        $end = $start->modify("+{$minutes} minutes");
        foreach ($this->calendar as $slot) {
            $from = self::moment($slot['when']);
            if (null !== $from && $from < $end && $from->modify("+{$slot['minutes']} minutes") > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * A moment written as YYYY-MM-DD HH:MM, or null.
     */
    public static function moment(string $when): ?\DateTimeImmutable
    {
        $moment = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($when));

        return false !== $moment ? $moment : null;
    }
}
