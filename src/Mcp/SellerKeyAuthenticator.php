<?php

namespace App\Mcp;

use App\Entity\Seller;
use Doctrine\ORM\EntityManagerInterface;
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
 * Un client MCP s'entraîne au nom d'un commercial : sa clé personnelle dans l'adresse (/mcp?key=…, le plus simple pour
 * ajouter un connecteur) ou en « Authorization: Bearer … ».
 */
final class SellerKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const HELP = 'Ajoutez votre adresse personnelle, affichée sur Sparring dans « Mon compte » : /mcp?key=…';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $key = $request->query->getString('key') ?: (preg_match('/^Bearer\s+(\S+)$/i', (string) $request->headers->get('Authorization'), $m) ? $m[1] : '');
        if ('' === $key) {
            throw new CustomUserMessageAuthenticationException(self::HELP);
        }
        $hash = hash('sha256', $key);

        return new SelfValidatingPassport(new UserBadge($hash, fn (): Seller => $this->em->getRepository(Seller::class)->findOneBy(['mcpKeyHash' => $hash]) ?? throw new CustomUserMessageAuthenticationException('Cette clé n\'appartient à personne. '.self::HELP)));
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
