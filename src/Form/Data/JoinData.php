<?php

declare(strict_types=1);

namespace App\Form\Data;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Joining the team through an invitation (the email comes from the invitation).
 */
final class JoinData
{
    #[Assert\NotBlank, Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank, Assert\Length(min: 8, max: 4096)]
    public string $password = '';
}
