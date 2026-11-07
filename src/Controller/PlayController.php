<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\ModelException;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Progress\MissionTracker;
use App\Progress\ProgressReport;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use App\Scenario\SurpriseCalls;
use App\Security\Voter\ScenarioVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The game: the member does not pick a case, they start a call; a situation is drawn and written for them, they read
 * their briefing, then pick up.
 */
final class PlayController extends AbstractController
{
    /**
     * The home page: level, missions, the "new call" button, what may come up, the last calls.
     */
    #[Route('/', name: 'home')]
    public function home(#[CurrentUser] Member $me, ProgressReport $progress, MissionTracker $missions, CallRepository $calls, CompanyRepository $companies): Response
    {
        return $this->render('play/home.html.twig', [
            'current' => $calls->current($me),
            'stats' => $progress->of($me),
            'missions' => $missions->of($me),
            'recent' => $calls->history($me, 6),
            'situations' => $companies->get()->situations(),
        ]);
    }

    /**
     * Draws and writes a surprise call (some fifteen seconds), then gives the address of its briefing.
     */
    #[Route('/call/new', name: 'call_draw', methods: ['POST'])]
    #[IsCsrfTokenValid('call-draw', tokenKey: 'token')]
    public function draw(#[CurrentUser] Member $me, Request $request, SurpriseCalls $calls, TranslatorInterface $translator): JsonResponse
    {
        set_time_limit(300);
        try {
            $scenario = $calls->draw($me, $request->request->getString('situation') ?: null);
        } catch (ModelException|\RuntimeException) {
            return new JsonResponse(['error' => $translator->trans('play.draw_failed')], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['url' => $this->generateUrl('call_briefing', ['id' => $scenario->id])]);
    }

    /**
     * The briefing before the call: the situation, the document, the objectives, who is on the line.
     */
    #[Route('/call/{id<\d+>}', name: 'call_briefing')]
    #[IsGranted(ScenarioVoter::VIEW, 'scenario')]
    public function briefing(Scenario $scenario): Response
    {
        return $this->render('play/briefing.html.twig', ['scenario' => $scenario]);
    }
}
