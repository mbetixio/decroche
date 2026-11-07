<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\Member;
use App\Entity\Scenario;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * The links an MCP client hands to the seller to take a call out loud in their browser: a signed, short-lived sign-in
 * link (Symfony's login link) that lands on the call's briefing, so the phone screen opens without a password.
 */
final readonly class CallLinks
{
    /** How long a call link stays valid, in seconds. */
    public const int LIFETIME = 900;

    public function __construct(
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private LoginLinkHandlerInterface $loginLinks,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function briefing(Member $member, Scenario $scenario): string
    {
        return $this->to($member, $this->urls->generate('call_briefing', ['id' => $scenario->id]));
    }

    private function to(Member $member, string $path): string
    {
        $url = $this->loginLinks->createLoginLink($member, lifetime: self::LIFETIME)->getUrl();

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['_target_path' => $path]);
    }
}
