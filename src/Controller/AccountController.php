<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends Controller
{
    /**
     * Le compte : le mot de passe, et la clé MCP personnelle pour s'entraîner depuis Claude ou ChatGPT (montrée une
     * seule fois, quand elle est créée).
     */
    #[Route('/compte', name: 'account')]
    public function account(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $me = $this->me();
        $key = null;
        if ($request->isMethod('POST')) {
            $this->checkToken('account', $request->request->getString('_token'));
            if ($request->request->has('mcp')) {
                $key = $me->newMcpKey();
                $em->flush();
            } elseif ($request->request->has('password')) {
                $password = $request->request->getString('password');
                if (!$hasher->isPasswordValid($me, $request->request->getString('current'))) {
                    $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                } elseif (mb_strlen($password) < 8) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit faire au moins 8 caractères.');
                } else {
                    $me->setPassword($hasher->hashPassword($me, $password));
                    $em->flush();
                    $this->addFlash('success', 'Mot de passe changé.');
                }

                return $this->redirectToRoute('account');
            }
        }

        return $this->render('account.html.twig', ['key' => $key, 'mcp' => $this->generateUrl('_mcp_endpoint_sparring', [], 0)]);
    }
}
