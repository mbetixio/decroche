<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Company;
use App\Model\Situation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The catalogue of situations, as edited by a manager.
 */
final class SituationsData
{
    /** @var list<SituationData> */
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'company.error.no_situation')]
    public array $situations = [];

    public static function of(Company $company): self
    {
        $data = new self();
        $data->situations = array_values(array_map(SituationData::of(...), $company->situations()));

        return $data;
    }

    /**
     * @return list<Situation> the situations kept
     */
    public function kept(): array
    {
        return array_values(array_filter(array_map(static fn (SituationData $s): ?Situation => $s->toSituation(), $this->situations)));
    }
}
