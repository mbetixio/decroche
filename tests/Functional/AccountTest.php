<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\MemberRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The member's account: the personal MCP address, shown once, that a new one replaces.
 */
final class AccountTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->signIn($this->member());
    }

    public function testThePasswordIsChanged(): void
    {
        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->selectButton('Change the password')->form(), ['change_password[current]' => 'wrong-password', 'change_password[password]' => 'a-new-password']);
        self::assertSelectorTextContains('main', 'This is not your current password.');

        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->selectButton('Change the password')->form(), ['change_password[current]' => 'password-123', 'change_password[password]' => 'a-new-password']);
        self::assertResponseRedirects('/account');
        $member = $this->service(MemberRepository::class)->findOneByEmail('seller@decroche.test');
        self::assertTrue($this->service(UserPasswordHasherInterface::class)->isPasswordValid($member, 'a-new-password'));
    }

    public function testThePersonalMcpAddressIsShownOnceAndCanBeReplaced(): void
    {
        $this->client->request('GET', '/account');
        $this->submit('Create my MCP address');
        self::assertResponseIsSuccessful();
        $first = (string) $this->client->getCrawler()->filter('#mcp-url')->attr('value');
        self::assertMatchesRegularExpression('#/mcp\\?key=pcl_[A-Za-z0-9_-]+$#', $first);
        $hash = $this->service(MemberRepository::class)->findOneByEmail('seller@decroche.test')?->mcpKeyHash;
        self::assertNotNull($hash);

        // On the next visit the address is no longer shown: only its hash is kept.
        $this->client->request('GET', '/account');
        self::assertSelectorNotExists('#mcp-url');

        $this->submit('New MCP address');
        $second = (string) $this->client->getCrawler()->filter('#mcp-url')->attr('value');
        self::assertNotSame($first, $second);
        self::assertNotSame($hash, $this->service(MemberRepository::class)->findOneByEmail('seller@decroche.test')?->mcpKeyHash);
    }
}
