<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\ModelException;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\MeetingType;
use App\Form\Model\PrepareData;
use App\Form\Model\ScenarioData;
use App\Form\PrepareType;
use App\Form\ScenarioType;
use App\Repository\CallRepository;
use App\Repository\ScenarioRepository;
use App\Scenario\ScenarioFactory;
use App\Scenario\ScenarioWriter;
use App\Security\Voter\ScenarioVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The scenario library (the managers' practice cases, and the meetings a seller prepared), and the rehearsal of a real
 * meeting from a prospect sheet or a past call's transcript.
 */
final class LibraryController extends AbstractController
{
    public function __construct(
        private readonly ScenarioRepository $scenarios,
    ) {
    }

    #[Route('/library', name: 'library')]
    public function library(#[CurrentUser] Member $me, Request $request): Response
    {
        $type = MeetingType::tryFrom($request->query->getString('type'));

        return $this->render('library/index.html.twig', ['scenarios' => $this->scenarios->library($me, $type), 'type' => $type, 'types' => MeetingType::cases()]);
    }

    #[Route('/library/{id<\d+>}', name: 'scenario_show')]
    #[IsGranted(ScenarioVoter::VIEW, 'scenario')]
    public function show(Scenario $scenario, #[CurrentUser] Member $me, CallRepository $calls): Response
    {
        return $this->render('library/show.html.twig', ['scenario' => $scenario, 'calls' => $calls->ofScenario($scenario, $me)]);
    }

    #[Route('/library/new', name: 'scenario_new')]
    #[IsGranted('ROLE_MANAGER')]
    public function new(#[CurrentUser] Member $me, Request $request, ClockInterface $clock): Response
    {
        $data = new ScenarioData();
        $form = $this->createForm(ScenarioType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $scenario = ScenarioFactory::fromArray($data->toArray(), $me, $clock->now());
            $this->scenarios->save($scenario);
            $this->addFlash('success', 'library.created');

            return $this->redirectToRoute('scenario_show', ['id' => $scenario->id]);
        }

        return $this->render('library/form.html.twig', ['form' => $form, 'scenario' => null]);
    }

    #[Route('/library/{id<\d+>}/edit', name: 'scenario_edit')]
    #[IsGranted(ScenarioVoter::EDIT, 'scenario')]
    public function edit(Scenario $scenario, Request $request): Response
    {
        $data = ScenarioData::of($scenario);
        $form = $this->createForm(ScenarioType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data->applyTo($scenario);
            $this->scenarios->save($scenario);
            $this->addFlash('success', 'library.saved');

            return $this->redirectToRoute('scenario_show', ['id' => $scenario->id]);
        }

        return $this->render('library/form.html.twig', ['form' => $form, 'scenario' => $scenario]);
    }

    /**
     * Archives a scenario (or brings it back): its calls stay, it is no longer offered.
     */
    #[Route('/library/{id<\d+>}/archive', name: 'scenario_archive', methods: ['POST'])]
    #[IsGranted(ScenarioVoter::EDIT, 'scenario')]
    #[IsCsrfTokenValid(new Expression('"archive-" ~ args["scenario"].id'))]
    public function archive(Scenario $scenario): Response
    {
        $scenario->active = !$scenario->active;
        $this->scenarios->save($scenario);
        $this->addFlash('success', $scenario->active ? 'library.restored' : 'library.archived');

        return $this->redirectToRoute($scenario->active ? 'scenario_show' : 'library', $scenario->active ? ['id' => $scenario->id] : []);
    }

    /**
     * Rehearses a real meeting: a scenario written from what is known of the prospect (about a minute).
     */
    #[Route('/prepare', name: 'prepare')]
    public function prepare(#[CurrentUser] Member $me, Request $request, ScenarioWriter $writer): Response
    {
        $data = new PrepareData();
        $form = $this->createForm(PrepareType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            set_time_limit(300);
            try {
                $scenario = $writer->prepare($data->text, $me, $data->type, $data->difficulty, $data->minutes, $data->fromCall);
                $this->scenarios->save($scenario);

                return $this->redirectToRoute('call_briefing', ['id' => $scenario->id]);
            } catch (ModelException|\InvalidArgumentException $e) {
                $this->addFlash('error', $e instanceof ModelException ? 'prepare.failed' : $e->getMessage());
            }
        }

        return $this->render('library/prepare.html.twig', ['form' => $form]);
    }
}
