<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Company;
use App\Model\Criterion;
use App\Model\Situation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The scorecard: two to eight criteria, each with what the coach expects.
 */
final class ScorecardData
{
    /** @var list<array{label: string, hint: string}> */
    #[Assert\Count(min: 2, max: 8, minMessage: 'company.error.scorecard_size', maxMessage: 'company.error.scorecard_size')]
    #[Assert\All([new Assert\Collection(fields: [
        'label' => [new Assert\NotBlank(), new Assert\Length(max: 60)],
        'hint' => [new Assert\Length(max: 300)],
    ])])]
    public array $criteria = [];

    public static function of(Company $company): self
    {
        $data = new self();
        $data->criteria = array_values(array_map(static fn (Criterion $c): array => ['label' => $c->label, 'hint' => $c->hint], $company->scorecard()));

        return $data;
    }

    /**
     * @return list<Criterion>
     */
    public function toCriteria(): array
    {
        $criteria = [];
        foreach ($this->criteria as $row) {
            $key = Situation::slug($row['label']);
            while (isset($criteria[$key])) {
                $key .= '_';
            }
            $criteria[$key] = new Criterion($key, trim($row['label']), trim($row['hint']));
        }

        return array_values($criteria);
    }
}
