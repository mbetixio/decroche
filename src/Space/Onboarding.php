<?php

namespace App\Space;

use App\Entity\Seller;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * L'arrivée dans Sparring : tant que l'espace de la société n'a pas sa fiche d'offre et ses situations, le manager est
 * conduit à son espace ; tant qu'un membre ne s'est pas décrit, il est conduit à son profil.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class Onboarding
{
    /** Les pages accessibles avant d'avoir fini l'arrivée. */
    private const OPEN = ['login', 'logout', 'register', 'join', 'space', 'space_analyze', 'space_situations', 'me', 'account', 'offer'];

    /** Les pages où l'on joue : il faut s'être présenté, les appels s'adaptent au profil. */
    private const PLAY = ['home', 'library', 'call_new', 'call', 'scenario_start', 'scenario_generate', 'training_say'];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || '' === $route || str_starts_with($route, '_') || \in_array($route, self::OPEN, true) || str_starts_with($request->getPathInfo(), '/mcp')) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof Seller || null === $user->workspace) {
            return;
        }
        $target = match (true) {
            !$user->workspace->isReady() && $user->isManager() => 'space',
            !$user->workspace->isReady() => 'me',
            // Un manager gère son espace et suit son équipe sans s'être présenté ; pour jouer, il se présente comme les autres.
            !$user->hasProfile() && (!$user->isManager() || \in_array($route, self::PLAY, true)) => 'me',
            default => null,
        };
        if (null !== $target) {
            $event->setResponse(new RedirectResponse($this->urls->generate($target)));
        }
    }
}
