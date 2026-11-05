<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Seller;
use App\Entity\Workspace;
use App\Space\Analyst;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Security $security,
    ) {
    }

    #[Route('/connexion', name: 'login')]
    public function login(AuthenticationUtils $auth): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('security/login.html.twig', ['last' => $auth->getLastUsername(), 'error' => $auth->getLastAuthenticationError()]);
    }

    /**
     * Une société ouvre son espace : celui qui l'ouvre en est le manager. Il décrit ensuite sa société et fait analyser
     * son site ; ses commerciaux arrivent par invitation.
     */
    #[Route('/inscription', name: 'register')]
    public function register(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }
        $error = null;
        if ($request->isMethod('POST')) {
            $company = trim($request->request->getString('company'));
            $website = trim($request->request->getString('website'));
            $error = $this->check($request, 'register') ?? match (true) {
                '' === $company => 'Indiquez le nom de votre société.',
                '' !== $website && !self::validSite($website) => 'Cette adresse de site n\'est pas valide.',
                default => null,
            };
            if (null === $error) {
                $space = new Workspace($company);
                $space->website = '' !== $website ? Analyst::normalize($website) : null;
                $this->em->persist($space);
                $seller = $this->account($request, Seller::MANAGER, $space);
                $this->security->login($seller, 'form_login', 'main');

                return $this->redirectToRoute('space');
            }
        }

        return $this->render('security/register.html.twig', ['error' => $error, 'values' => $request->request->all()]);
    }

    /**
     * Rejoindre l'espace d'une société par le lien d'invitation : on crée son compte, puis on se présente.
     */
    #[Route('/rejoindre/{token<[a-f0-9]{48}>}', name: 'join')]
    public function join(string $token, Request $request): Response
    {
        $invitation = $this->em->getRepository(Invitation::class)->findOneBy(['token' => $token]);
        if (null === $invitation || null !== $invitation->acceptedAt) {
            return $this->render('security/join.html.twig', ['invitation' => null, 'error' => null, 'values' => []], new Response('', 404));
        }
        $error = null;
        if ($request->isMethod('POST')) {
            $request->request->set('email', $invitation->email);
            $error = $this->check($request, 'join'.$invitation->id);
            if (null === $error) {
                $seller = $this->account($request, $invitation->role, $invitation->workspace);
                $invitation->acceptedAt = new \DateTimeImmutable();
                $this->em->flush();
                $this->security->login($seller, 'form_login', 'main');

                return $this->redirectToRoute('me');
            }
        }

        return $this->render('security/join.html.twig', ['invitation' => $invitation, 'error' => $error, 'values' => $request->request->all()]);
    }

    #[Route('/deconnexion', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    private function check(Request $request, string $token): ?string
    {
        $email = mb_strtolower(trim($request->request->getString('email')));

        return match (true) {
            !$this->isCsrfTokenValid($token, $request->request->getString('_token')) => 'Le formulaire a expiré, recommencez.',
            '' === trim($request->request->getString('name')) => 'Indiquez votre nom.',
            !filter_var($email, \FILTER_VALIDATE_EMAIL) => 'Cette adresse e-mail n\'est pas valide.',
            mb_strlen($request->request->getString('password')) < 8 => 'Le mot de passe doit faire au moins 8 caractères.',
            null !== $this->em->getRepository(Seller::class)->findOneBy(['email' => $email]) => 'Un compte existe déjà avec cette adresse.',
            default => null,
        };
    }

    private function account(Request $request, string $role, Workspace $space): Seller
    {
        $seller = new Seller(trim($request->request->getString('name')), $request->request->getString('email'), $role, $space);
        $seller->setPassword($this->hasher->hashPassword($seller, $request->request->getString('password')));
        $this->em->persist($seller);
        $this->em->flush();

        return $seller;
    }

    private static function validSite(string $url): bool
    {
        try {
            Analyst::normalize($url);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
