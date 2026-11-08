<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\MemberRole;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * A member's role and the security roles it grants.
 */
final class MemberRoleTest extends TestCase
{
    public function testSecurityRoles(): void
    {
        self::assertSame(['ROLE_USER'], MemberRole::Seller->securityRoles());
        self::assertSame(['ROLE_USER', 'ROLE_MANAGER'], MemberRole::Manager->securityRoles());
    }

    public function testTranslationKey(): void
    {
        self::assertSame('enum.member_role.manager', MemberRole::Manager->trans(new IdentityTranslator()));
    }
}
