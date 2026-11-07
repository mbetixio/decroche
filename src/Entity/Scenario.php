<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Persona;
use App\Model\Situation;
use App\Repository\ScenarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A practice meeting: what the seller knows (the context, the goal, the persona) and the persona's hidden brief,
 * which never leaves the server before the debrief. Either written for the library, prepared from a real prospect, or
 * drawn as a surprise call from one of the company's situations.
 */
#[ORM\Entity(repositoryClass: ScenarioRepository::class)]
class Scenario
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    /** Archived scenarios leave the library but keep their calls. */
    #[ORM\Column]
    public bool $active = true;

    /** @var array<string, mixed>|null the situation drawn for a surprise call (a snapshot: the catalogue may change) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $situation = null;

    /** What the seller has in front of them before the call: the job ad, the form message, the client's email… */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $material = null;

    /** @var list<string> checkable objectives that the debrief declares reached or not */
    #[ORM\Column(type: Types::JSON)]
    private array $objectives = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $persona;

    /** @var array<string, string> what the persona knows and hides, by {@see BriefSection}, plus their side of the story ("setup") */
    #[ORM\Column(type: Types::JSON)]
    private array $brief;

    /**
     * @param array<string, string> $brief
     */
    public function __construct(
        #[ORM\Column(length: 160)]
        public string $title,
        #[ORM\Column(enumType: MeetingType::class, length: 30)]
        public MeetingType $type,
        #[ORM\Column(enumType: Difficulty::class)]
        public Difficulty $difficulty,
        /** The longest the meeting may last. */
        #[ORM\Column]
        public int $minutes,
        /** What the seller knows going in: who, why this meeting. */
        #[ORM\Column(type: Types::TEXT)]
        public string $context,
        /** What they must obtain for the meeting to be a success. */
        #[ORM\Column(type: Types::TEXT)]
        public string $goal,
        Persona $persona,
        /** The persona's first line, which opens the call. */
        #[ORM\Column(type: Types::TEXT)]
        public string $opening,
        array $brief,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Member $author = null,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->persona = $persona->toArray();
        $this->setBrief($brief);
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    public function persona(): Persona
    {
        return Persona::fromArray($this->persona);
    }

    public function setPersona(Persona $persona): void
    {
        $this->persona = $persona->toArray();
    }

    /**
     * @param array<string, string> $brief
     */
    public function setBrief(array $brief): void
    {
        $this->brief = array_filter(array_map('trim', $brief), static fn (string $v): bool => '' !== $v);
    }

    public function brief(BriefSection $section): string
    {
        return $this->brief[$section->value] ?? '';
    }

    /**
     * The persona's side of the story: what they know of the seller's company, what they are doing when the phone
     * rings, their mood. Hidden like the rest of the brief.
     */
    public function setup(): string
    {
        return $this->brief['setup'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function briefArray(): array
    {
        return $this->brief;
    }

    public function situation(): ?Situation
    {
        return null === $this->situation ? null : Situation::fromArray((string) ($this->situation['key'] ?? ''), $this->situation);
    }

    public function setSituation(?Situation $situation): void
    {
        $this->situation = $situation?->toArray();
    }

    public function isSurpriseCall(): bool
    {
        return null !== $this->situation;
    }

    /**
     * @return list<string>
     */
    public function objectives(): array
    {
        return $this->objectives;
    }

    /**
     * @param list<string> $objectives
     */
    public function setObjectives(array $objectives): void
    {
        $this->objectives = \array_slice(Situation::strings($objectives), 0, 4);
    }

    /**
     * A library scenario (written by a manager) belongs to the whole team; one a seller prepared from a prospect, or
     * a surprise call drawn for them, stays theirs and their managers'.
     */
    public function visibleTo(Member $member): bool
    {
        return null === $this->author || $this->author->isManager() || $member->isManager() || $this->author === $member;
    }

    public function editableBy(Member $member): bool
    {
        return $member->isManager() || (null !== $this->author && $this->author === $member);
    }

    /**
     * What the seller (and their AI, over MCP) may see: never the brief. Enums are given by value; the caller
     * translates them.
     *
     * @return array<string, mixed>
     */
    public function publicView(): array
    {
        $persona = $this->persona();
        $situation = $this->situation();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'minutes' => $this->minutes,
            'context' => $this->context,
            'goal' => $this->goal,
            'persona' => ['name' => $persona->name, 'role' => $persona->role, 'company' => $persona->company],
        ] + (null !== $situation ? [
            'situation' => $situation->label,
            'direction' => $situation->direction->value,
            'material' => $this->material,
            'objectives' => $this->objectives,
        ] : []);
    }
}
