<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Mission;
use App\Entity\Seller;
use App\Training\Progress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProgressController extends Controller
{
    public function __construct(
        private readonly Progress $progress,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/progression', name: 'progress')]
    public function mine(): Response
    {
        return $this->seller($this->me());
    }

    #[Route('/equipe', name: 'team')]
    public function team(): Response
    {
        $space = $this->space();
        $invitations = $this->em->getRepository(Invitation::class)->findBy(['workspace' => $space, 'acceptedAt' => null], ['createdAt' => 'DESC']);

        return $this->render('team.html.twig', $this->progress->team($space) + ['space' => $space, 'invitations' => $invitations, 'missions' => $this->progress->teamMissions($space), 'members' => $this->em->getRepository(Seller::class)->findBy(['workspace' => $space], ['name' => 'ASC'])]);
    }

    #[Route('/equipe/{id<\d+>}', name: 'team_seller')]
    public function seller(Seller $seller): Response
    {
        $this->colleague($seller);
        return $this->render('progress.html.twig', ['seller' => $seller, 'stats' => $this->progress->of($seller), 'history' => $this->progress->history($seller)]);
    }

    /**
     * Le manager invite quelqu'un dans l'espace : un lien personnel à lui transmettre, qui lui fait créer son compte.
     */
    #[Route('/equipe/inviter', name: 'team_invite', methods: ['POST'])]
    public function invite(Request $request): Response
    {
        $this->checkToken('team_invite', $request->request->getString('_token'));
        $email = mb_strtolower(trim($request->request->getString('email')));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Cette adresse e-mail n\'est pas valide.');
        } elseif (null !== $this->em->getRepository(Seller::class)->findOneBy(['email' => $email])) {
            $this->addFlash('error', 'Un compte existe déjà avec cette adresse.');
        } else {
            $invitation = new Invitation($this->space(), $email, $request->request->getBoolean('manager') ? Seller::MANAGER : Seller::SELLER, $this->me());
            $this->em->persist($invitation);
            $this->em->flush();
            $this->addFlash('success', 'Invitation prête pour '.$email.' : envoyez-lui le lien ci-dessous.');
        }

        return $this->redirectToRoute('team');
    }

    /**
     * Le manager donne une mission : tant d'appels, telle note, telle situation, avant telle date.
     */
    #[Route('/equipe/missions', name: 'team_mission', methods: ['POST'])]
    public function mission(Request $request): Response
    {
        $this->checkToken('team_mission', $request->request->getString('_token'));
        $space = $this->space();
        $assignee = $request->request->getInt('assignee') > 0 ? $this->em->find(Seller::class, $request->request->getInt('assignee')) : null;
        if (null !== $assignee && $assignee->workspace !== $space) {
            throw $this->createNotFoundException();
        }
        $situation = $request->request->getString('situation');
        $due = $request->request->getString('due');
        $mission = new Mission(
            $space,
            $assignee,
            isset($space->situations[$situation]) ? $situation : null,
            $request->request->getInt('calls', 3),
            $request->request->getInt('target', 70),
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? new \DateTimeImmutable($due) : null,
            mb_substr(trim($request->request->getString('note')), 0, 200),
            $this->me(),
            \Symfony\Component\Clock\Clock::get()->now(),
        );
        $this->em->persist($mission);
        $this->em->flush();
        $this->addFlash('success', 'Mission donnée'.(null !== $assignee ? ' à '.$assignee->name : ' à toute l\'équipe').'.');

        return $this->redirectToRoute('team');
    }

    #[Route('/equipe/missions/{id<\d+>}/retirer', name: 'team_mission_remove', methods: ['POST'])]
    public function removeMission(Mission $mission, Request $request): Response
    {
        $this->checkToken('mission'.$mission->id, $request->request->getString('_token'));
        if ($mission->workspace !== $this->space()) {
            throw $this->createNotFoundException();
        }
        $this->em->remove($mission);
        $this->em->flush();
        $this->addFlash('success', 'Mission retirée.');

        return $this->redirectToRoute('team');
    }

    #[Route('/equipe/invitations/{id<\d+>}/retirer', name: 'team_invite_cancel', methods: ['POST'])]
    public function cancel(Invitation $invitation, Request $request): Response
    {
        $this->checkToken('cancel'.$invitation->id, $request->request->getString('_token'));
        if ($invitation->workspace !== $this->space()) {
            throw $this->createNotFoundException();
        }
        $this->em->remove($invitation);
        $this->em->flush();
        $this->addFlash('success', 'Invitation retirée.');

        return $this->redirectToRoute('team');
    }

    #[Route('/equipe/{id<\d+>}/mot-de-passe', name: 'team_password', methods: ['POST'])]
    public function password(Seller $seller, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->colleague($seller);
        $this->checkToken('password'.$seller->id, $request->request->getString('_token'));
        $password = self::temporary();
        $seller->setPassword($hasher->hashPassword($seller, $password));
        $em->flush();
        $this->addFlash('success', \sprintf('Nouveau mot de passe provisoire pour %s : %s', $seller->name, $password));

        return $this->redirectToRoute('team_seller', ['id' => $seller->id]);
    }

    private static function temporary(): string
    {
        return implode('-', str_split(bin2hex(random_bytes(6)), 4));
    }

    #[Route('/equipe/{id<\d+>}/role', name: 'team_role', methods: ['POST'])]
    public function role(Seller $seller, Request $request, EntityManagerInterface $em): Response
    {
        $this->colleague($seller);
        $this->checkToken('role'.$seller->id, $request->request->getString('_token'));
        if ($seller === $this->me()) {
            $this->addFlash('error', 'Vous ne pouvez pas changer votre propre rôle.');
        } else {
            $seller->role = $seller->isManager() ? Seller::SELLER : Seller::MANAGER;
            $em->flush();
            $this->addFlash('success', $seller->name.' est maintenant '.($seller->isManager() ? 'manager' : 'commercial').'.');
        }

        return $this->redirectToRoute('team');
    }

    /**
     * Un manager ne voit et ne gère que les membres de son espace.
     */
    private function colleague(Seller $seller): void
    {
        if ($seller->workspace !== $this->space()) {
            throw $this->createNotFoundException();
        }
    }
}
