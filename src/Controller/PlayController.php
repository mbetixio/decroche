<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\ModelException;
use App\Call\CallException;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Http\ProgressStream;
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
    #[Route('/', name: 'home', methods: ['GET'])]
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
     * Draws and writes a surprise call (some fifteen seconds), then gives the address of its briefing. A debrief may
     * ask for a call that trains one criterion of the scorecard ("focus").
     */
    #[Route('/call/new', name: 'call_draw', methods: ['POST'])]
    #[IsCsrfTokenValid('call-draw', tokenKey: 'token')]
    public function draw(#[CurrentUser] Member $me, Request $request, SurpriseCalls $calls, TranslatorInterface $translator, ProgressStream $progress): Response
    {
        set_time_limit(300);
        $situation = $request->request->getString('situation') ?: null;
        $focus = $request->request->getString('focus') ?: null;
        if (ProgressStream::wanted($request)) {
            return $progress->response(
                $request,
                fn (\Closure $step): string => $this->generateUrl('call_briefing', ['id' => $calls->draw($me, $situation, $focus, $step)->id]),
                static fn (): array => ['error' => $translator->trans('play.draw_failed')],
            );
        }
        // A script asks for JSON; a form sent without JavaScript gets pages.
        $json = 'json' === $request->getPreferredFormat();
        try {
            $scenario = $calls->draw($me, $situation, $focus);
        } catch (CallException|ModelException|\RuntimeException $e) {
            $message = $e instanceof CallException ? $e->getMessage() : 'play.draw_failed';
            if (!$json) {
                $this->addFlash('error', $message);

                return $this->redirectToRoute('home');
            }

            return new JsonResponse(['error' => $translator->trans($message)], $e instanceof CallException ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_BAD_GATEWAY);
        }
        $url = $this->generateUrl('call_briefing', ['id' => $scenario->id]);

        return $json ? new JsonResponse(['url' => $url]) : $this->redirect($url);
    }

    /**
     * The briefing before the call: the situation, the document, the objectives, who is on the line.
     */
    #[Route('/call/{id<\d+>}', name: 'call_briefing', methods: ['GET'])]
    #[IsGranted(ScenarioVoter::VIEW, 'scenario')]
    public function briefing(Scenario $scenario, CompanyRepository $companies): Response
    {
        return $this->render('play/briefing.html.twig', [
            'scenario' => $scenario,
            'focus' => null !== $scenario->focus ? ($companies->get()->scorecard()[$scenario->focus] ?? null) : null,
        ]);
    }
}
