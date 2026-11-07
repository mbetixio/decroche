<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Company;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The company's identity: its name, its website, what it says about itself.
 */
final class CompanyData
{
    #[Assert\NotBlank, Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^(https?:\/\/)?[^\s\/.]+\.[^\s]+$/i', message: 'company.error.invalid_website')]
    public ?string $website = null;

    #[Assert\Length(max: 5000)]
    public string $about = '';

    public static function of(Company $company): self
    {
        $data = new self();
        $data->name = $company->name;
        $data->website = $company->website;
        $data->about = $company->about;

        return $data;
    }
}
