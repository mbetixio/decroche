<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\ModelException;
use App\Company\InstanceReset;
use App\Company\OfferAnalyst;
use App\Form\CompanyType;
use App\Form\Data\CompanyData;
use App\Form\Data\OfferData;
use App\Form\Data\ScorecardData;
use App\Form\Data\SituationsData;
use App\Form\OfferType;
use App\Form\ScorecardType;
use App\Form\SituationsType;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The company settings, for managers: its identity, the offer sheet, the catalogue of situations, the scorecard, a new
 * analysis of the website, and a reset of the whole instance. The same forms as the onboarding, on tabs.
 */
#[Route('/settings')]
#[IsGranted('ROLE_MANAGER')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'settings', methods: ['GET', 'POST'])]
    public function company(Request $request): Response
    {
        $company = $this->companies->get();
        $identity = CompanyData::of($company);
        $identityForm = $this->createForm(CompanyType::class, $identity);
        $identityForm->handleRequest($request);
        if ($identityForm->isSubmitted() && $identityForm->isValid()) {
            $identity->applyTo($company);
            $this->em->flush();
            $this->addFlash('success', 'settings.saved');

            return $this->redirectToRoute('settings');
        }

        $offer = OfferData::of($company);
        $offerForm = $this->createForm(OfferType::class, $offer);
        $offerForm->handleRequest($request);
        if ($offerForm->isSubmitted() && $offerForm->isValid()) {
            $offer->applyTo($company);
            $this->em->flush();
            $this->addFlash('success', 'settings.saved');

            return $this->redirectToRoute('settings', ['_fragment' => 'offer']);
        }

        return $this->render('settings/company.html.twig', ['tab' => 'company', 'identityForm' => $identityForm, 'offerForm' => $offerForm]);
    }

    #[Route('/situations', name: 'settings_situations', methods: ['GET', 'POST'])]
    public function situations(Request $request): Response
    {
        $company = $this->companies->get();
        $data = SituationsData::of($company);
        $form = $this->createForm(SituationsType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ([] === $data->kept()) {
                $this->addFlash('error', 'company.error.no_situation');
            } else {
                $company->setSituations($data->kept());
                $this->em->flush();
                $this->addFlash('success', 'settings.saved');

                return $this->redirectToRoute('settings_situations');
            }
        }

        return $this->render('settings/situations.html.twig', ['tab' => 'situations', 'form' => $form]);
    }

    #[Route('/scorecard', name: 'settings_scorecard', methods: ['GET', 'POST'])]
    public function scorecard(Request $request): Response
    {
        $company = $this->companies->get();
        $data = ScorecardData::of($company);
        $form = $this->createForm(ScorecardType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $company->setScorecard($data->toCriteria());
            $this->em->flush();
            $this->addFlash('success', 'settings.saved');

            return $this->redirectToRoute('settings_scorecard');
        }

        return $this->render('settings/scorecard.html.twig', ['tab' => 'scorecard', 'form' => $form]);
    }

    /**
     * Analyses the website again: a new offer sheet, and situations added for what the catalogue lacks.
     */
    #[Route('/analyze', name: 'settings_analyze', methods: ['POST'])]
    #[IsCsrfTokenValid('settings-analyze')]
    public function analyze(OfferAnalyst $analyst): Response
    {
        set_time_limit(400);
        try {
            $analyst->analyze($this->companies->get(), keep: true);
            $this->em->flush();
            $this->addFlash('success', 'settings.analyzed');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (ModelException|\RuntimeException) {
            $this->addFlash('error', 'onboarding.analysis.failed');
        }

        return $this->redirectToRoute('settings', ['_fragment' => 'offer']);
    }

    /**
     * Empties the instance and starts the onboarding again: everything goes, so the manager types a word to confirm.
     */
    #[Route('/reset', name: 'settings_reset', methods: ['POST'])]
    #[IsCsrfTokenValid('settings-reset')]
    public function reset(Request $request, InstanceReset $instance, TokenStorageInterface $tokens): Response
    {
        $typed = mb_strtoupper(trim($request->request->getString('confirm')));
        if ($typed !== mb_strtoupper($this->translator->trans('settings.reset.word'))) {
            $this->addFlash('error', 'settings.reset.unconfirmed');

            return $this->redirectToRoute('settings', ['_fragment' => 'reset']);
        }
        $instance->reset();
        $tokens->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('onboarding');
    }
}
