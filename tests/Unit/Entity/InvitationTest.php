<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Invitation;
use App\Enum\MemberRole;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * An invitation: normalized email, a random token, pending until accepted.
 */
final class InvitationTest extends TestCase
{
    use Fixtures;

    public function testNewInvitation(): void
    {
        $manager = self::member(role: MemberRole::Manager);
        $invitation = new Invitation(' Bob@Example.com ', MemberRole::Seller, $manager, self::at());

        self::assertSame('bob@example.com', $invitation->email);
        self::assertSame(MemberRole::Seller, $invitation->role);
        self::assertSame($manager, $invitation->invitedBy);
        self::assertEquals(self::at(), $invitation->createdAt);
        self::assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $invitation->token);
        self::assertTrue($invitation->isPending());
        self::assertNull($invitation->acceptedAt);
    }

    public function testTokensAreUnique(): void
    {
        self::assertNotSame(new Invitation('a@example.com', MemberRole::Seller, null, self::at())->token, new Invitation('a@example.com', MemberRole::Seller, null, self::at())->token);
    }

    public function testAccept(): void
    {
        $invitation = new Invitation('bob@example.com', MemberRole::Manager, null, self::at());
        $invitation->accept(self::at('2026-11-14 08:00:00'));

        self::assertFalse($invitation->isPending());
        self::assertEquals(self::at('2026-11-14 08:00:00'), $invitation->acceptedAt);
    }
}
