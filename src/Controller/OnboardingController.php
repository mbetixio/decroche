<?php

declare(strict_types=1);

namespace App\Controller;

use App\Company\DemoCompany;
use App\Company\OfferAnalyst;
use App\Company\TeamManager;
use App\Entity\Company;
use App\Entity\Member;
use App\Enum\Language;
use App\Enum\MemberRole;
use App\Form\AccountType;
use App\Form\CompanyType;
use App\Form\InvitationsType;
use App\Form\LanguageType;
use App\Form\Model\AccountData;
use App\Form\Model\CompanyData;
use App\Form\Model\InvitationsData;
use App\Form\Model\OfferData;
use App\Form\Model\ScorecardData;
use App\Form\Model\SituationsData;
use App\Form\OfferType;
use App\Form\ScorecardType;
use App\Form\SituationsType;
use App\Model\Criterion;
use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use App\Repository\InvitationRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The first-boot onboarding, step by step: the working language, the first manager's account, the company (its
 * website analysed, or the example company), the offer sheet, the situations, the scorecard, the team, then the first
 * call. Each step can be revisited; none can be skipped ahead.
 */
#[Route('/welcome')]
final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly MemberRepository $members,
        private readonly InvitationRepository $invitations,
        private readonly TeamManager $teamManager,
        private readonly DemoCompany $demo,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/{step}', name: 'onboarding', requirements: ['step' => new EnumRequirement(OnboardingStep::class)], defaults: ['step' => 'welcome'], methods: ['GET', 'POST'])]
    public function step(OnboardingStep $step, Request $request): Response
    {
        $company = $this->companies->get();
        if (null !== $redirect = $this->guard($step, $company)) {
            return $redirect;
        }

        return match ($step) {
            OnboardingStep::Welcome => $this->welcome($request, $company),
            OnboardingStep::Account => $this->account($request, $company),
            OnboardingStep::Company => $this->company($request, $company),
            OnboardingStep::Analysis => $this->render('onboarding/analysis.html.twig', ['step' => $step]),
            OnboardingStep::Offer => $this->offer($request, $company),
            OnboardingStep::Situations => $this->situations($request, $company),
            OnboardingStep::Scorecard => $this->scorecard($request, $company),
            OnboardingStep::Team => $this->team($request, $company),
            OnboardingStep::Done => $this->done($company),
        };
    }

    /**
     * The analysis of the website, run by the analysis screen (a minute or two): the offer sheet, then the situations.
     */
    #[Route('/analysis/run', name: 'onboarding_analyze', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    #[IsCsrfTokenValid('onboarding-analysis', tokenKey: 'token')]
    public function analyze(OfferAnalyst $analyst): JsonResponse
    {
        set_time_limit(400);
        $company = $this->companies->get();
        try {
            $analyst->analyze($company);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $this->translator->trans($e->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            // The model or the website failed: the screen offers to try again.
            return new JsonResponse(['error' => $this->translator->trans('onboarding.analysis.failed')], Response::HTTP_BAD_GATEWAY);
        }
        $company->reach(OnboardingStep::Offer);
        $this->em->flush();

        return new JsonResponse(['next' => $this->generateUrl('onboarding', ['step' => OnboardingStep::Offer->value])]);
    }

    /**
     * While a manager sets the instance up, sellers wait here.
     */
    #[Route('/wait', name: 'onboarding_waiting', methods: ['GET'], priority: 1)]
    public function waiting(): Response
    {
        return $this->render('onboarding/waiting.html.twig');
    }

    /**
     * Completes the onboarding.
     */
    #[Route('/finish', name: 'onboarding_finish', methods: ['POST'], priority: 1)]
    #[IsGranted('ROLE_MANAGER')]
    #[IsCsrfTokenValid('onboarding-finish')]
    public function finish(): Response
    {
        $company = $this->companies->get();
        $company->completeOnboarding($this->clock->now());
        $this->em->flush();
        $this->addFlash('success', 'onboarding.done.flash');

        return $this->redirectToRoute('home');
    }

    /**
     * Keeps visitors on the steps they may see: the public ones before any account exists, then only a manager's, and
     * never a step ahead of the furthest one reached.
     */
    private function guard(OnboardingStep $step, Company $company): ?Response
    {
        $hasAccount = $this->members->hasAny();
        if (OnboardingStep::Account === $step && $hasAccount) {
            return $this->getUser() instanceof Member ? $this->redirectToRoute('onboarding', ['step' => OnboardingStep::Company->value]) : $this->redirectToRoute('login');
        }
        if (!$step->isPublic() && !$this->isGranted('ROLE_MANAGER')) {
            return $hasAccount ? $this->redirectToRoute('login') : $this->redirectToRoute('onboarding', ['step' => $company->step->value]);
        }
        if (!$company->hasReached($step)) {
            return $this->redirectToRoute('onboarding', ['step' => $company->step->value]);
        }

        return null;
    }

    private function welcome(Request $request, Company $company): Response
    {
        // The language being previewed (?lang=…) is the one ticked.
        $form = $this->createForm(LanguageType::class, ['language' => Language::tryFrom($request->getLocale()) ?? $company->language]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $language = $form->get('language')->getData();
            $company->language = $language instanceof Language ? $language : $company->language;
            $company->reach(OnboardingStep::Account);
            $this->em->flush();

            return $this->next(OnboardingStep::Welcome);
        }

        return $this->render('onboarding/welcome.html.twig', ['form' => $form, 'step' => OnboardingStep::Welcome]);
    }

    private function account(Request $request, Company $company): Response
    {
        $data = new AccountData();
        $form = $this->createForm(AccountType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $member = $this->teamManager->create($data->name, $data->email, $data->password, MemberRole::Manager, $data->profile);
            $this->security->login($member, 'form_login', 'main');
            $company->reach(OnboardingStep::Company);
            $this->em->flush();

            return $this->next(OnboardingStep::Account);
        }

        return $this->render('onboarding/account.html.twig', ['form' => $form, 'step' => OnboardingStep::Account]);
    }

    private function company(Request $request, Company $company): Response
    {
        $data = CompanyData::of($company);
        $form = $this->createForm(CompanyType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data->applyTo($company);

            // The example company: no analysis, its offer, situations and library are loaded at once.
            if ('example' === $request->request->getString('action')) {
                $this->demo->apply($company);
                $this->demo->loadLibrary($company->language);
                $company->reach(OnboardingStep::Offer);
                $this->em->flush();

                return $this->redirectToRoute('onboarding', ['step' => OnboardingStep::Offer->value]);
            }
            if (null === $company->website && mb_strlen($company->about) < 30) {
                $form->addError(new FormError($this->translator->trans('company.error.nothing_to_analyze')));
            } else {
                $company->reach(OnboardingStep::Analysis);
                $this->em->flush();

                return $this->next(OnboardingStep::Company);
            }
        }

        return $this->render('onboarding/company.html.twig', ['form' => $form, 'step' => OnboardingStep::Company]);
    }

    private function offer(Request $request, Company $company): Response
    {
        $data = OfferData::of($company);
        $form = $this->createForm(OfferType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data->applyTo($company);
            $company->reach(OnboardingStep::Situations);
            $this->em->flush();

            return $this->next(OnboardingStep::Offer);
        }

        return $this->render('onboarding/offer.html.twig', ['form' => $form, 'step' => OnboardingStep::Offer]);
    }

    private function situations(Request $request, Company $company): Response
    {
        $data = SituationsData::of($company);
        $form = $this->createForm(SituationsType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && [] !== $data->kept()) {
            $company->setSituations($data->kept());
            $company->reach(OnboardingStep::Scorecard);
            $this->em->flush();

            return $this->next(OnboardingStep::Situations);
        }

        return $this->render('onboarding/situations.html.twig', ['form' => $form, 'step' => OnboardingStep::Situations]);
    }

    private function scorecard(Request $request, Company $company): Response
    {
        $data = ScorecardData::of($company);
        $form = $this->createForm(ScorecardType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $company->setScorecard($data->toCriteria());
            $company->reach(OnboardingStep::Team);
            $this->em->flush();

            return $this->next(OnboardingStep::Scorecard);
        }

        return $this->render('onboarding/scorecard.html.twig', ['form' => $form, 'step' => OnboardingStep::Scorecard, 'defaults' => Criterion::defaults($company->language)]);
    }

    private function team(Request $request, Company $company): Response
    {
        $data = new InvitationsData();
        $form = $this->createForm(InvitationsType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $this->getUser();
            foreach ($data->addresses() as $email) {
                try {
                    $this->teamManager->invite($email, $data->role, $manager instanceof Member ? $manager : null);
                } catch (\DomainException) {
                    // Someone who already has an account needs no invitation.
                }
            }
            if ('continue' === $request->request->getString('action')) {
                $company->reach(OnboardingStep::Done);
                $this->em->flush();

                return $this->next(OnboardingStep::Team);
            }

            return $this->redirectToRoute('onboarding', ['step' => OnboardingStep::Team->value]);
        }

        return $this->render('onboarding/team.html.twig', [
            'form' => $form,
            'step' => OnboardingStep::Team,
            'invitations' => $this->invitations->pending(),
        ]);
    }

    private function done(Company $company): Response
    {
        return $this->render('onboarding/done.html.twig', ['step' => OnboardingStep::Done, 'company' => $company]);
    }

    private function next(OnboardingStep $step): Response
    {
        return $this->redirectToRoute('onboarding', ['step' => ($step->next() ?? OnboardingStep::Done)->value]);
    }
}
