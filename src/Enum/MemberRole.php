<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a member may do in their company's workspace.
 */
enum MemberRole: string implements TranslatableInterface
{
    /** Practises calls and follows their own progress. */
    case Seller = 'seller';

    /** Also runs the workspace: offer sheet, situations, scorecard, team, invitations, missions. */
    case Manager = 'manager';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.member_role.'.$this->value, locale: $locale);
    }

    /**
     * @return list<string> the security roles granted to this member
     */
    public function securityRoles(): array
    {
        return match ($this) {
            self::Seller => ['ROLE_USER'],
            self::Manager => ['ROLE_USER', 'ROLE_MANAGER'],
        };
    }
}
