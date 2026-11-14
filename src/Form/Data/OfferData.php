<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Company;
use App\Model\Situation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The offer sheet and the typical clients (one per line), as edited by a manager.
 */
final class OfferData
{
    #[Assert\NotBlank, Assert\Length(min: 80, max: 20000)]
    public string $offer = '';

    #[Assert\Length(max: 3000)]
    public string $sectors = '';

    public static function of(Company $company): self
    {
        $data = new self();
        $data->offer = $company->offer;
        $data->sectors = implode("\n", $company->sectors);

        return $data;
    }

    /**
     * Writes the offer sheet and the typical clients onto the company.
     */
    public function applyTo(Company $company): void
    {
        $company->offer = trim($this->offer);
        $company->setSectors(Situation::strings($this->sectors, 30));
    }
}
