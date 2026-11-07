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
    #[Route('/progress', name: 'progress')]
    public function progress(#[CurrentUser] Member $me, ProgressReport $progress, MissionTracker $missions, CallRepository $calls, CompanyRepository $companies): Response
    {
        return $this->render('progress/index.html.twig', [
            'member' => $me,
            'stats' => $progress->of($me),
            'missions' => $missions->of($me),
            'recent' => $calls->history($me, 12),
            'situations' => $companies->get()->situations(),
        ]);
    }

    #[Route('/offer', name: 'offer')]
    public function offer(CompanyRepository $companies): Response
    {
        return $this->render('progress/offer.html.twig', ['company' => $companies->get()]);
    }
}
