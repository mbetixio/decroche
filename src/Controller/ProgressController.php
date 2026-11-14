<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Member;
use App\Progress\MissionTracker;
use App\Progress\ProgressReport;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The member's progress, and the company's offer sheet (what the personas know of it).
 */
final class ProgressController extends AbstractController
{
    #[Route('/progress', name: 'progress', methods: ['GET'])]
    public function progress(#[CurrentUser] Member $me, ProgressReport $progress, MissionTracker $missions, CallRepository $calls, CompanyRepository $companies): Response
    {
        // The member's calls, read once for the figures, the missions and the list.
        $history = $calls->history($me);

        return $this->render('progress/index.html.twig', [
            'member' => $me,
            'stats' => $progress->of($me, $history),
            'missions' => $missions->of($me, $history),
            'recent' => \array_slice($history, 0, 12),
            'situations' => $companies->get()->situations(),
        ]);
    }

    #[Route('/offer', name: 'offer', methods: ['GET'])]
    public function offer(CompanyRepository $companies): Response
    {
        return $this->render('progress/offer.html.twig', ['company' => $companies->get()]);
    }
}
