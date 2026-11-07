<?php

declare(strict_types=1);

namespace App\Form\Model;

use App\Enum\MemberRole;
use App\Model\Situation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * People to invite: one email per line, and the role they get.
 */
final class InvitationsData
{
    #[Assert\Length(max: 5000)]
    public string $emails = '';

    public MemberRole $role = MemberRole::Seller;

    /**
     * @return list<string> the valid addresses given
     */
    public function addresses(): array
    {
        $list = Situation::strings(preg_split('/[\s,;]+/', $this->emails) ?: [], 100);

        return array_values(array_filter($list, static fn (string $email): bool => false !== filter_var($email, \FILTER_VALIDATE_EMAIL)));
    }
}
