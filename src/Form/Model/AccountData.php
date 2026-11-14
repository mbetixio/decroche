<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The first manager's account, created during the onboarding (a guest's comes from {@see JoinData}).
 */
final class AccountData
{
    #[Assert\NotBlank, Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank, Assert\Length(min: 8, max: 4096)]
    public string $password = '';

    /** Who they are: their calls adapt to it. */
    #[Assert\NotBlank, Assert\Length(max: 2000)]
    public string $profile = '';
}
