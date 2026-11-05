<?php

namespace App\Controller;

use App\Entity\Scenario;
use App\Game\Situations;
use App\Training\Progress;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends Controller
{
    #[Route('/', name: 'home')]
    public function home(Progress $progress, Trainer $trainer): Response
    {
        $me = $this->me();

        return $this->render('play.html.twig', [
            'current' => $trainer->current($me),
            'stats' => $progress->of($me),
            'recent' => $progress->history($me, 8),
            'situations' => $me->space()->situations,
            'missions' => $progress->missions($me, \Symfony\Component\Clock\Clock::get()->now()),
        ]);
    }

    /**
     * L'offre de la société, en fiche : ce que le persona connaît, ce que le coach vérifie, ce que le commercial doit savoir.
     */
    #[Route('/offre', name: 'offer')]
    public function offer(): Response
    {
        return $this->render('offer.html.twig', ['space' => $this->space(), 'offer' => $this->space()->offer, 'situations' => $this->space()->situations]);
    }

    #[Route('/bibliotheque', name: 'library')]
    public function library(Request $request, EntityManagerInterface $em, Progress $progress, Trainer $trainer): Response
    {
        $me = $this->me();
        $category = $request->query->getString('type');
        $criteria = ['active' => true, 'situation' => null, 'workspace' => $me->space()] + (isset(Scenario::CATEGORIES[$category]) ? ['category' => $category] : []);
        $scenarios = array_values(array_filter($em->getRepository(Scenario::class)->findBy($criteria, ['difficulty' => 'ASC', 'title' => 'ASC']), static fn (Scenario $s): bool => $s->visibleTo($me)));

        return $this->render('library.html.twig', [
            'scenarios' => $scenarios,
            'category' => $category,
            'current' => $trainer->current($me),
            'stats' => $progress->of($me),
            'recent' => [],
        ]);
    }
}
