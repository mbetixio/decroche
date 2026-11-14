<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Form\Data\JoinData;
use App\Form\JoinType;
use App\Repository\InvitationRepository;
use App\Repository\MemberRepository;
use App\Team\TeamManager;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Signing in and out, the call links' sign-in, and joining the team by invitation.
 */
final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth, MemberRepository $members): Response
    {
        if ($this->getUser() instanceof Member) {
            return $this->redirectToRoute('home');
        }
        // On first boot nobody can sign in yet: the onboarding creates the first account.
        if (!$members->hasAny()) {
            return $this->redirectToRoute('onboarding');
        }

        return $this->render('security/login.html.twig', ['last' => $auth->getLastUsername(), 'error' => $auth->getLastAuthenticationError()]);
    }

    /**
     * Never reached: the firewall handles the sign-out.
     */
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    /**
     * Never reached: the firewall checks the signed link and signs the member in.
     */
    #[Route('/login/link', name: 'login_link_check', methods: ['GET'])]
    public function loginLink(): never
    {
        throw new \LogicException('Intercepted by the firewall.');
    }

    /**
     * Joining the team through the link of an invitation: the account is created, then the member describes themself.
     */
    #[Route('/join/{token<[a-f0-9]{48}>}', name: 'join', methods: ['GET', 'POST'])]
    public function join(string $token, Request $request, InvitationRepository $invitations, TeamManager $team, Security $security, ClockInterface $clock): Response
    {
        $invitation = $invitations->findPendingByToken($token);
        // Unknown, used, or more than two weeks old: the manager sends a new one.
        if (null === $invitation || !$invitation->isValid($clock->now())) {
            return $this->render('security/join.html.twig', ['invitation' => null], new Response(status: Response::HTTP_NOT_FOUND));
        }
        $data = new JoinData();
        $form = $this->createForm(JoinType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $member = $team->accept($invitation, $data->name, $data->password);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('login');
            }
            $security->login($member, 'form_login', 'main');

            return $this->redirectToRoute('profile');
        }

        return $this->render('security/join.html.twig', ['invitation' => $invitation, 'form' => $form]);
    }
}
