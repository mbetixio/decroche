<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Member;
use App\Entity\Mission;
use App\Form\Data\InvitationsData;
use App\Form\Data\MissionData;
use App\Form\InvitationsType;
use App\Form\MissionType;
use App\Progress\MissionTracker;
use App\Progress\ProgressReport;
use App\Progress\TeamReport;
use App\Repository\CallRepository;
use App\Repository\InvitationRepository;
use App\Team\MissionManager;
use App\Team\TeamManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The managers' side: the team board, each member's figures, invitations, missions, roles and passwords.
 */
#[Route('/team')]
#[IsGranted('ROLE_MANAGER')]
final class TeamController extends AbstractController
{
    public function __construct(
        private readonly TeamManager $team,
        private readonly MissionManager $missionManager,
        private readonly ProgressReport $progress,
        private readonly MissionTracker $missions,
        private readonly CallRepository $calls,
    ) {
    }

    #[Route('', name: 'team', methods: ['GET', 'POST'])]
    public function board(#[CurrentUser] Member $me, Request $request, TeamReport $report, InvitationRepository $invitations): Response
    {
        $invite = new InvitationsData();
        $inviteForm = $this->createForm(InvitationsType::class, $invite);
        $inviteForm->handleRequest($request);
        if ($inviteForm->isSubmitted() && $inviteForm->isValid()) {
            $sent = 0;
            foreach ($invite->addresses() as $email) {
                try {
                    $this->team->invite($email, $invite->role, $me);
                    ++$sent;
                } catch (\DomainException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
            if ($sent > 0) {
                $this->addFlash('success', 'team.invited');
            }

            return $this->redirectToRoute('team', ['_fragment' => 'invitations']);
        }

        $mission = new MissionData();
        $missionForm = $this->createForm(MissionType::class, $mission);
        $missionForm->handleRequest($request);
        if ($missionForm->isSubmitted() && $missionForm->isValid()) {
            $this->missionManager->give($mission->assignee, $mission->situation, $mission->calls, $mission->target, $mission->dueAt, $mission->note, $me);
            $this->addFlash('success', 'team.mission_given');

            return $this->redirectToRoute('team', ['_fragment' => 'missions']);
        }

        return $this->render('team/board.html.twig', [
            'board' => $report->build(),
            'missions' => $this->missions->team(),
            'invitations' => $invitations->pending(),
            'inviteForm' => $inviteForm,
            'missionForm' => $missionForm,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'team_member', methods: ['GET'])]
    public function member(Member $member): Response
    {
        return $this->memberPage($member);
    }

    #[Route('/{id<\d+>}/role', name: 'team_role', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"role-" ~ args["member"].id'))]
    public function role(Member $member, #[CurrentUser] Member $me): Response
    {
        try {
            $this->team->toggleRole($member, $me);
            $this->addFlash('success', 'team.role_changed');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('team_member', ['id' => $member->id]);
    }

    /**
     * A new password for a member who lost theirs, shown once to the manager.
     */
    #[Route('/{id<\d+>}/password', name: 'team_password', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"password-" ~ args["member"].id'))]
    public function password(Member $member): Response
    {
        $response = $this->memberPage($member, $this->team->resetPassword($member));
        // A secret: rendered rather than redirected to (it would have to wait in the session), and never cached.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/invitations/{id<\d+>}/withdraw', name: 'team_invitation_withdraw', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"withdraw-" ~ args["invitation"].id'))]
    public function withdraw(Invitation $invitation, Request $request): Response
    {
        $this->team->withdraw($invitation);
        // Back to where it was withdrawn (the team page or the onboarding), never to another site.
        $back = (string) $request->headers->get('referer');

        return $this->redirect(str_starts_with($back, $request->getSchemeAndHttpHost().'/') ? $back : $this->generateUrl('team'));
    }

    #[Route('/missions/{id<\d+>}/remove', name: 'team_mission_remove', methods: ['POST'])]
    #[IsCsrfTokenValid(new Expression('"mission-" ~ args["mission"].id'))]
    public function removeMission(Mission $mission): Response
    {
        $this->missionManager->remove($mission);

        return $this->redirectToRoute('team', ['_fragment' => 'missions']);
    }

    /**
     * A member's figures, missions and last calls; with the temporary password just given to them, if any.
     */
    private function memberPage(Member $member, ?string $password = null): Response
    {
        $history = $this->calls->history($member);

        return $this->render('team/member.html.twig', [
            'member' => $member,
            'stats' => $this->progress->of($member, $history),
            'missions' => $this->missions->of($member, $history),
            'recent' => \array_slice($history, 0, 20),
        ] + (null !== $password ? ['password' => $password] : []));
    }
}
