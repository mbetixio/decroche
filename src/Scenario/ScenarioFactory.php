<?php

declare(strict_types=1);

namespace App\Scenario;

use App\Ai\ModelException;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Persona;
use App\Model\Situation;

/**
 * Builds a scenario from loose data: what the model wrote, a YAML library entry, or a manager's form.
 */
final class ScenarioFactory
{
    /**
     * @param array<mixed> $data title, type, difficulty, minutes, context, goal, persona, opening, brief, material, objectives
     *
     * @throws ModelException when the essentials are missing (a title, the persona's name, their first line, their pain)
     */
    public static function fromArray(array $data, ?Member $author, \DateTimeImmutable $now): Scenario
    {
        $text = static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '';
        $persona = Persona::fromArray(\is_array($data['persona'] ?? null) ? $data['persona'] : []);
        $brief = [];
        $given = \is_array($data['brief'] ?? null) ? $data['brief'] : [];
        foreach ([...array_map(static fn (BriefSection $s): string => $s->value, BriefSection::cases()), 'setup'] as $key) {
            $value = $given[$key] ?? '';
            $brief[$key] = \is_array($value) ? implode(' ; ', Situation::strings($value)) : $text($value);
        }
        $title = $text($data['title'] ?? '');
        $opening = trim($text($data['opening'] ?? ''), " \t\n«»\"“”");
        if ('' === $title || '' === $persona->name || '' === $opening || '' === $brief[BriefSection::Pain->value]) {
            throw new ModelException('The scenario is incomplete: a title, the persona\'s name, their first line and their real pain are required.');
        }

        $scenario = new Scenario(
            title: $title,
            type: MeetingType::tryFrom($text($data['type'] ?? $data['category'] ?? '')) ?? MeetingType::Discovery,
            difficulty: Difficulty::tryFrom((int) ($data['difficulty'] ?? 2)) ?? Difficulty::Demanding,
            minutes: max(3, min(60, (int) ($data['minutes'] ?? 12))),
            context: $text($data['context'] ?? ''),
            goal: $text($data['goal'] ?? ''),
            persona: $persona,
            opening: $opening,
            brief: $brief,
            author: $author,
            now: $now,
        );
        $scenario->material = '' !== $text($data['material'] ?? '') ? $text($data['material']) : null;
        $scenario->setObjectives(Situation::strings($data['objectives'] ?? []));

        return $scenario;
    }
}
