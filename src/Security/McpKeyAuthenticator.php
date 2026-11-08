<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Member;
use App\Repository\MemberRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates an MCP client on behalf of a member: their personal key in the address (/mcp?key=…) or as a bearer
 * token. Failures answer as JSON-RPC errors, which MCP clients understand.
 */
final class McpKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string HELP = 'Add your personal address, shown in Practicall under "My account": /mcp?key=… / Ajoutez votre adresse personnelle, affichée dans « Mon compte » : /mcp?key=…';

    public function __construct(
        private readonly MemberRepository $members,
    ) {
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $key = $request->query->getString('key')
            ?: (preg_match('/^Bearer\s+(\S+)$/i', (string) $request->headers->get('Authorization'), $m) ? $m[1] : '');
        if ('' === $key) {
            throw new CustomUserMessageAuthenticationException(self::HELP);
        }

        return new SelfValidatingPassport(new UserBadge(hash('sha256', $key), fn (): Member => $this->members->findOneByMcpKey($key)
            ?? throw new CustomUserMessageAuthenticationException('Unknown key / Clé inconnue. '.self::HELP)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return self::deny($exception->getMessageKey());
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return self::deny(self::HELP);
    }

    private static function deny(string $message): Response
    {
        return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => $message]], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
