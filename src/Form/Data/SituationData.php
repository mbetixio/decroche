<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Enum\CallDirection;
use App\Enum\SituationFamily;
use App\Model\Situation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One situation of the catalogue, as edited by a manager (kept or removed).
 */
final class SituationData
{
    public string $key = '';

    public bool $keep = true;

    #[Assert\NotBlank(groups: ['kept']), Assert\Length(max: 80)]
    public string $label = '';

    #[Assert\Length(max: 4)]
    public string $emoji = '📞';

    public SituationFamily $family = SituationFamily::Prospecting;

    public CallDirection $direction = CallDirection::Outgoing;

    /** What the seller has in front of them: a short description of the document ("the website form: name, company, message"). */
    #[Assert\Length(max: 600)]
    public string $material = '';

    #[Assert\NotBlank(groups: ['kept']), Assert\Length(min: 20, max: 2000, groups: ['kept'])]
    public string $pitch = '';

    /** One per line. */
    #[Assert\NotBlank(groups: ['kept'])]
    public string $objectives = '';

    public static function of(Situation $situation): self
    {
        $data = new self();
        $data->key = $situation->key;
        $data->label = $situation->label;
        $data->emoji = $situation->emoji;
        $data->family = $situation->family;
        $data->direction = $situation->direction;
        $data->material = $situation->material;
        $data->pitch = $situation->pitch;
        $data->objectives = implode("\n", $situation->objectives);

        return $data;
    }

    public function toSituation(): ?Situation
    {
        if (!$this->keep) {
            return null;
        }

        return Situation::fromArray('' !== $this->key ? $this->key : $this->label, [
            'label' => $this->label,
            'emoji' => $this->emoji,
            'family' => $this->family->value,
            'direction' => $this->direction->value,
            'material' => $this->material,
            'pitch' => $this->pitch,
            'objectives' => $this->objectives,
        ]);
    }
}
