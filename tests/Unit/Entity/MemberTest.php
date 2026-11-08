<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Member;
use App\Enum\MemberRole;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * A member: email normalization, roles, profile, initials, personal MCP keys, and what goes into the session.
 */
final class MemberTest extends TestCase
{
    use Fixtures;

    public function testEmailIsNormalized(): void
    {
        $member = new Member('Alice', '  Alice.Martin@Example.COM ');

        self::assertSame('alice.martin@example.com', $member->email);
        self::assertSame('alice.martin@example.com', $member->getUserIdentifier());
    }

    public function testCreatedAt(): void
    {
        self::assertEquals(self::at(), self::member()->createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, new Member('Alice', 'a@example.com')->createdAt);
    }

    public function testRoles(): void
    {
        $seller = self::member();
        $manager = self::member(role: MemberRole::Manager);

        self::assertFalse($seller->isManager());
        self::assertSame(['ROLE_USER'], $seller->getRoles());
        self::assertTrue($manager->isManager());
        self::assertSame(['ROLE_USER', 'ROLE_MANAGER'], $manager->getRoles());
    }

    public function testProfile(): void
    {
        $member = self::member();
        self::assertFalse($member->hasProfile());

        $member->profile = '   ';
        self::assertFalse($member->hasProfile());

        $member->profile = 'Sells dev teams to CTOs.';
        self::assertTrue($member->hasProfile());
    }

    public function testInitials(): void
    {
        self::assertSame('AM', self::member('alice martin')->initials());
        self::assertSame('JL', self::member('Jean-Luc Picard Second')->initials());
        self::assertSame('É', self::member(' élise ')->initials());
    }

    public function testPassword(): void
    {
        $member = self::member();
        self::assertSame('', $member->getPassword());

        $member->setPassword('$2y$hash');
        self::assertSame('$2y$hash', $member->getPassword());
    }

    public function testNewMcpKey(): void
    {
        $member = self::member();
        self::assertNull($member->mcpKeyHash);

        $key = $member->newMcpKey();
        self::assertMatchesRegularExpression('/^spr_[0-9a-f]{40}$/', $key);
        self::assertSame(hash('sha256', $key), $member->mcpKeyHash);

        $next = $member->newMcpKey();
        self::assertNotSame($key, $next);
        self::assertSame(hash('sha256', $next), $member->mcpKeyHash);
    }

    public function testSerializationNeverContainsThePasswordHash(): void
    {
        $member = self::member();
        $member->setPassword('$2y$13$very-secret-password-hash');
        $data = $member->__serialize();
        $serialized = serialize($member);

        self::assertStringNotContainsString('very-secret-password-hash', $serialized);
        self::assertSame(hash('crc32c', '$2y$13$very-secret-password-hash'), $data["\0".Member::class."\0password"]);
        self::assertStringContainsString('alice@example.com', $serialized);

        $back = unserialize($serialized);
        self::assertInstanceOf(Member::class, $back);
        self::assertSame('alice@example.com', $back->email);
        self::assertNotSame($member->getPassword(), $back->getPassword());
    }

    public function testAChangedPasswordChangesTheSerialization(): void
    {
        $member = self::member();
        $member->setPassword('one');
        $before = serialize($member);
        $member->setPassword('two');

        self::assertNotSame($before, serialize($member));
    }
}
