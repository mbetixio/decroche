<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Entity\Member;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Who a member is: their calls and their coach adapt to it.
 */
final class ProfileData
{
    #[Assert\NotBlank, Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotBlank, Assert\Length(max: 2000)]
    public string $profile = '';

    public static function of(Member $member): self
    {
        $data = new self();
        $data->name = $member->name;
        $data->profile = $member->profile;

        return $data;
    }
}
