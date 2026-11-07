<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Scenario;
use App\Enum\BriefSection;
use App\Enum\Difficulty;
use App\Enum\MeetingType;
use App\Model\Persona;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A library scenario, as written or edited by a manager.
 */
final class ScenarioData
{
    #[Assert\NotBlank, Assert\Length(max: 160)]
    public string $title = '';

    public MeetingType $type = MeetingType::Discovery;

    public Difficulty $difficulty = Difficulty::Demanding;

    #[Assert\Range(min: 3, max: 60)]
    public int $minutes = 10;

    #[Assert\NotBlank]
    public string $context = '';

    #[Assert\NotBlank]
    public string $goal = '';

    #[Assert\NotBlank, Assert\Length(max: 120)]
    public string $personaName = '';

    public string $personaRole = '';

    public string $personaCompany = '';

    public string $personaPersonality = '';

    #[Assert\NotBlank]
    public string $opening = '';

    /** @var array<string, string> by {@see BriefSection} value */
    #[Assert\Collection(fields: ['pain' => new Assert\NotBlank()], allowExtraFields: true)]
    public array $brief = [];

    public static function of(Scenario $scenario): self
    {
        $data = new self();
        $persona = $scenario->persona();
        $data->title = $scenario->title;
        $data->type = $scenario->type;
        $data->difficulty = $scenario->difficulty;
        $data->minutes = $scenario->minutes;
        $data->context = $scenario->context;
        $data->goal = $scenario->goal;
        $data->personaName = $persona->name;
        $data->personaRole = $persona->role;
        $data->personaCompany = $persona->company;
        $data->personaPersonality = $persona->personality;
        $data->opening = $scenario->opening;
        foreach (BriefSection::cases() as $section) {
            $data->brief[$section->value] = $scenario->brief($section);
        }

        return $data;
    }

    /**
     * @return array<string, mixed> for {@see \App\Scenario\ScenarioFactory::fromArray()}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title, 'type' => $this->type->value, 'difficulty' => $this->difficulty->value, 'minutes' => $this->minutes,
            'context' => $this->context, 'goal' => $this->goal, 'opening' => $this->opening, 'brief' => $this->brief,
            'persona' => ['name' => $this->personaName, 'role' => $this->personaRole, 'company' => $this->personaCompany, 'personality' => $this->personaPersonality],
        ];
    }

    /**
     * Applies the edits to an existing scenario (what the persona goes through on their side is kept).
     */
    public function applyTo(Scenario $scenario): void
    {
        $scenario->title = trim($this->title);
        $scenario->type = $this->type;
        $scenario->difficulty = $this->difficulty;
        $scenario->minutes = $this->minutes;
        $scenario->context = trim($this->context);
        $scenario->goal = trim($this->goal);
        $scenario->opening = trim($this->opening);
        $scenario->setPersona(new Persona(trim($this->personaName), trim($this->personaRole), trim($this->personaCompany), trim($this->personaPersonality), $scenario->persona()->gender, $scenario->persona()->tone));
        $scenario->setBrief(array_map(trim(...), $this->brief) + ['setup' => $scenario->setup()]);
    }
}
