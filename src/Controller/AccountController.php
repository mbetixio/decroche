<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\Data;
use App\Company\TeamManager;
use App\Entity\Member;
use App\Form\ChangePasswordType;
use App\Form\Model\ProfileData;
use App\Form\ProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * The member's own space: who they are (their profile, which adapts every call to them), their password, and their
 * personal MCP address, to train from Claude or any MCP client.
 */
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly TeamManager $team,
    ) {
    }

    #[Route('/me', name: 'profile', methods: ['GET', 'POST'])]
    public function profile(#[CurrentUser] Member $me, Request $request): Response
    {
        $data = ProfileData::of($me);
        $form = $this->createForm(ProfileType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $first = !$me->hasProfile();
            $data->applyTo($me);
            $this->team->save($me);
            $this->addFlash('success', $first ? 'account.welcome' : 'account.saved');

            return $this->redirectToRoute($first ? 'home' : 'account');
        }

        return $this->render('account/profile.html.twig', ['form' => $form, 'first' => !$me->hasProfile()]);
    }

    #[Route('/account', name: 'account', methods: ['GET', 'POST'])]
    public function account(#[CurrentUser] Member $me, Request $request): Response
    {
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->team->changePassword($me, Data::string($form->get('password')->getData()));
            $this->addFlash('success', 'account.password_changed');

            return $this->redirectToRoute('account');
        }

        return $this->render('account/index.html.twig', ['form' => $form, 'key' => null]);
    }

    /**
     * A new personal MCP key (the previous one stops working), shown once.
     */
    #[Route('/account/mcp-key', name: 'account_mcp_key', methods: ['POST'])]
    #[IsCsrfTokenValid('mcp-key')]
    public function mcpKey(#[CurrentUser] Member $me): Response
    {
        $response = $this->render('account/index.html.twig', ['form' => $this->createForm(ChangePasswordType::class), 'key' => $this->team->newMcpKey($me)]);
        // A secret: rendered rather than redirected to (it would have to wait in the session), and never cached.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
