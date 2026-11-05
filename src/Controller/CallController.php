<?php

namespace App\Controller;

use App\Entity\Scenario;
use App\Game\Situations;
use App\Training\Progress;
use App\Training\ScenarioWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le jeu : le commercial ne choisit pas, il lance une session ; une situation est tirée au hasard et écrite pour lui,
 * il lit sa fiche, puis décroche.
 */
final class CallController extends Controller
{
    /**
     * Tire et écrit une nouvelle situation (une dizaine de secondes), puis renvoie l'adresse de sa fiche.
     */
    #[Route('/appel/nouveau', name: 'call_new', methods: ['POST'])]
    public function new(Request $request, ScenarioWriter $writer, Progress $progress, EntityManagerInterface $em): Response
    {
        $this->checkToken('call', $request->headers->get('X-CSRF-Token') ?? $request->request->getString('_token'));
        set_time_limit(300);
        $me = $this->me();
        $level = $progress->of($me)['level']['number'];
        $recent = $progress->recent($me);
        $draw = Situations::draw($me->space(), $level, $request->query->getString('type') ?: null, $recent['keys']);
        try {
            $scenario = $writer->situation($draw, $me, $recent['names']);
        } catch (\Throwable) {
            // Une réponse mal formée : on retente une fois, avec un autre tirage.
            try {
                $scenario = $writer->situation(Situations::draw($me->space(), $level, null, $recent['keys']), $me, $recent['names']);
            } catch (\Throwable) {
                return new JsonResponse(['error' => 'Le modèle n\'a pas pu écrire la situation : réessayez dans un instant.'], 502);
            }
        }
        $em->persist($scenario);
        $em->flush();
        $url = $this->generateUrl('call', ['id' => $scenario->id]);

        return $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'json') ? new JsonResponse(['url' => $url]) : $this->redirect($url);
    }

    /**
     * La fiche avant l'appel : la situation, le document, les objectifs, l'interlocuteur.
     */
    #[Route('/appel/{id<\d+>}', name: 'call')]
    public function briefing(Scenario $scenario): Response
    {
        if (!$scenario->visibleTo($this->me())) {
            throw $this->createNotFoundException();
        }

        return $this->render('call/briefing.html.twig', ['s' => $scenario]);
    }
}
