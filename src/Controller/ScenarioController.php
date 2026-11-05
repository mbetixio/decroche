<?php

namespace App\Controller;

use App\Entity\Scenario;
use App\Entity\Training;
use App\Training\ScenarioWriter;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScenarioController extends Controller
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/scenarios/{id<\d+>}', name: 'scenario')]
    public function show(Scenario $scenario): Response
    {
        $this->see($scenario);
        $sessions = $this->em->getRepository(Training::class)->findBy(['scenario' => $scenario, 'seller' => $this->me()], ['startedAt' => 'DESC'], 10);

        return $this->render('scenario/show.html.twig', ['s' => $scenario, 'sessions' => $sessions]);
    }

    #[Route('/scenarios/{id<\d+>}/lancer', name: 'scenario_start', methods: ['POST'])]
    public function start(Scenario $scenario, Request $request, Trainer $trainer): Response
    {
        $this->see($scenario);
        $this->checkToken('start'.$scenario->id, $request->request->getString('_token'));
        $current = $trainer->current($this->me());
        if (null !== $current && $current->isLive()) {
            $trainer->abandon($current);
        }

        return $this->redirectToRoute('training', ['id' => $trainer->start($this->me(), $scenario)->id]);
    }

    #[Route('/scenarios/nouveau', name: 'scenario_new')]
    #[Route('/scenarios/{id<\d+>}/modifier', name: 'scenario_edit')]
    public function edit(Request $request, ?Scenario $scenario = null): Response
    {
        if (null === $scenario) {
            $this->denyAccessUnlessGranted('ROLE_MANAGER');
        } elseif (!$scenario->editableBy($this->me())) {
            throw $this->createAccessDeniedException();
        }
        $error = null;
        $values = null === $scenario ? ['difficulty' => 2, 'minutes' => 10, 'category' => 'decouverte', 'persona' => [], 'brief' => []] : [
            'title' => $scenario->title, 'category' => $scenario->category, 'difficulty' => $scenario->difficulty, 'minutes' => $scenario->minutes,
            'context' => $scenario->context, 'goal' => $scenario->goal, 'opening' => $scenario->opening, 'persona' => $scenario->persona, 'brief' => $scenario->brief,
        ];
        if ($request->isMethod('POST')) {
            $this->checkToken('scenario', $request->request->getString('_token'));
            $values = $request->request->all();
            try {
                $built = ScenarioWriter::fromArray($values, $scenario->author ?? $this->me());
                if (null === $scenario) {
                    $scenario = $built;
                    $this->em->persist($scenario);
                } else {
                    foreach (['title', 'category', 'difficulty', 'minutes', 'context', 'goal', 'persona', 'opening', 'brief'] as $field) {
                        $scenario->{$field} = $built->{$field};
                    }
                }
                $this->em->flush();
                $this->addFlash('success', 'Scénario enregistré.');

                return $this->redirectToRoute('scenario', ['id' => $scenario->id]);
            } catch (\RuntimeException) {
                $error = 'Il manque l\'essentiel : un titre, le nom du persona, sa première phrase et sa vraie douleur.';
            }
        }

        return $this->render('scenario/form.html.twig', ['s' => $scenario, 'v' => $values, 'error' => $error]);
    }

    /**
     * La génération assistée : une idée ou une fiche prospect, et l'IA écrit le scénario et son brief caché.
     */
    #[Route('/scenarios/generer', name: 'scenario_generate')]
    public function generate(Request $request, ScenarioWriter $writer): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $this->checkToken('generate', $request->request->getString('_token'));
            set_time_limit(300);
            try {
                $scenario = $writer->write($request->request->getString('idea'), $this->me(), $request->request->getString('category'), $request->request->getInt('difficulty', 2), $request->request->getInt('minutes', 12), 'call' === $request->request->getString('source'));
                $this->em->persist($scenario);
                $this->em->flush();
                $this->addFlash('success', $this->me()->isManager() ? 'Scénario généré : relisez-le avant de le proposer à l\'équipe.' : 'Scénario généré. Son brief est caché : à vous de le découvrir.');

                return $this->redirectToRoute($this->me()->isManager() ? 'scenario_edit' : 'scenario', ['id' => $scenario->id]);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (\Throwable) {
                $error = 'Le modèle n\'a pas pu écrire ce scénario : réessayez dans un instant.';
            }
        }

        return $this->render('scenario/generate.html.twig', ['error' => $error, 'v' => $request->request->all()]);
    }

    #[Route('/scenarios/{id<\d+>}/archiver', name: 'scenario_archive', methods: ['POST'])]
    public function archive(Scenario $scenario, Request $request): Response
    {
        if (!$scenario->editableBy($this->me())) {
            throw $this->createAccessDeniedException();
        }
        $this->checkToken('archive'.$scenario->id, $request->request->getString('_token'));
        $scenario->active = !$scenario->active;
        $this->em->flush();
        $this->addFlash('success', $scenario->active ? 'Scénario remis dans la bibliothèque.' : 'Scénario archivé.');

        return $this->redirectToRoute('scenario', ['id' => $scenario->id]);
    }

    private function see(Scenario $scenario): void
    {
        if (!$scenario->visibleTo($this->me())) {
            throw $this->createNotFoundException();
        }
    }
}
