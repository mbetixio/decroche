<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a member may do in their company's workspace.
 */
enum MemberRole: string
{
    /** Practises calls and follows their own progress. */
    case Seller = 'seller';

    /** Also runs the workspace: offer sheet, situations, scorecard, team, invitations, missions. */
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Seller => 'commercial',
            self::Manager => 'manager',
        };
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
