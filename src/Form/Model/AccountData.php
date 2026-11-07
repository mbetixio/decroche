<?php

declare(strict_types=1);

namespace App\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A new account: the first manager's (onboarding) or a guest's (invitation).
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
