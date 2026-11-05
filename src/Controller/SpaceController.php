<?php

namespace App\Controller;

use App\Game\Situations;
use App\Space\Analyst;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'espace de la société (pour son manager) : qui elle est, son site analysé, sa fiche d'offre et ses situations,
 * à corriger à la main. Et le profil de chacun : qui il est, pour que ses appels lui ressemblent.
 */
final class SpaceController extends Controller
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Analyst $analyst,
    ) {
    }

    #[Route('/espace', name: 'space')]
    public function edit(Request $request): Response
    {
        $space = $this->space();
        if ($request->isMethod('POST')) {
            $this->checkToken('space', $request->request->getString('_token'));
            $name = trim($request->request->getString('name'));
            if ('' !== $name) {
                $space->name = $name;
            }
            $website = trim($request->request->getString('website'));
            try {
                $space->website = '' !== $website ? Analyst::normalize($website) : null;
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
            $space->about = trim($request->request->getString('about'));
            if ($request->request->has('offer')) {
                $space->offer = trim($request->request->getString('offer'));
                $space->sectors = Analyst::strings($request->request->getString('sectors'), 30);
                $space->situations = self::situations($request->request->all('situations'), $space->situations);
                $space->criteria = self::scorecard($request->request->all('criteria'));
            }
            $this->em->flush();
            $this->addFlash('success', 'Espace enregistré.');

            return $this->redirectToRoute('space');
        }

        return $this->render('space/edit.html.twig', ['space' => $space, 'families' => Situations::FAMILIES]);
    }

    /**
     * L'analyse : le site et la description deviennent la fiche d'offre et le catalogue de situations (une minute ou deux).
     */
    #[Route('/espace/analyser', name: 'space_analyze', methods: ['POST'])]
    public function analyze(Request $request): Response
    {
        $this->checkToken('space', $request->request->getString('_token'));
        set_time_limit(400);
        $space = $this->space();
        $website = trim($request->request->getString('website'));
        try {
            if ('' !== $website) {
                $space->website = Analyst::normalize($website);
            }
            if ($request->request->has('about')) {
                $space->about = trim($request->request->getString('about'));
            }
            $this->em->flush();
            $this->analyst->analyze($space);
            $this->em->flush();
            $this->addFlash('success', 'Analyse terminée : relisez la fiche d\'offre et les situations, corrigez ce qui ne va pas, enregistrez.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'L\'analyse n\'a pas abouti (le modèle ou le site n\'a pas répondu) : réessayez dans un instant.');
        }

        return $this->redirectToRoute('space');
    }

    /**
     * Réécrire les situations depuis la fiche d'offre corrigée.
     */
    #[Route('/espace/situations', name: 'space_situations', methods: ['POST'])]
    public function situationsAgain(Request $request): Response
    {
        $this->checkToken('space', $request->request->getString('_token'));
        set_time_limit(300);
        try {
            $this->analyst->situations($this->space());
            $this->em->flush();
            $this->addFlash('success', 'Nouveau catalogue de situations, écrit depuis votre fiche d\'offre.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Les situations n\'ont pas pu être réécrites : réessayez.');
        }

        return $this->redirectToRoute('space');
    }

    /**
     * Mon profil : qui je suis, ce que je vends, à qui ; les situations et le coach s'y adaptent.
     */
    #[Route('/moi', name: 'me')]
    public function profile(Request $request): Response
    {
        $me = $this->me();
        if ($request->isMethod('POST')) {
            $this->checkToken('me', $request->request->getString('_token'));
            $name = trim($request->request->getString('name'));
            if ('' !== $name) {
                $me->name = $name;
            }
            $me->profile = trim($request->request->getString('profile'));
            $this->em->flush();
            if (!$me->hasProfile()) {
                $this->addFlash('error', 'Décrivez-vous en quelques phrases : c\'est ce qui adapte vos appels.');

                return $this->redirectToRoute('me');
            }
            $this->addFlash('success', 'Profil enregistré.');

            return $this->redirectToRoute('home');
        }

        return $this->render('space/me.html.twig', ['me' => $me, 'space' => $me->space()]);
    }

    /**
     * La grille de notation de la société : de 2 à 8 critères, chacun avec ce que le coach attend. Sans grille valable,
     * c'est celle de Sparring qui s'applique.
     *
     * @param array<mixed> $posted
     *
     * @return array<string, array{label: string, hint: string}>|null
     */
    private static function scorecard(array $posted): ?array
    {
        $grid = [];
        foreach ($posted as $key => $c) {
            $label = \is_array($c) && \is_string($c['label'] ?? null) ? trim($c['label']) : '';
            if ('' === $label || \count($grid) >= 8) {
                continue;
            }
            $key = \is_string($key) && preg_match('/^[a-z][a-z0-9_]{1,27}$/', $key) && !str_starts_with($key, 'nouveau_') ? $key : (substr((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $label))), 0, 28) ?: 'critere');
            $key = ltrim($key, '_0123456789') ?: 'critere';
            while (isset($grid[$key])) {
                $key .= '_';
            }
            $grid[$key] = ['label' => mb_substr($label, 0, 60), 'hint' => mb_substr(trim(\is_string($c['hint'] ?? null) ? $c['hint'] : ''), 0, 300)];
        }

        return \count($grid) >= 2 ? $grid : null;
    }

    /**
     * Le catalogue corrigé à la main : chaque situation gardée, modifiée, retirée, ou ajoutée.
     *
     * @param array<mixed>                                                                                                                     $posted
     * @param array<string, array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}> $before
     *
     * @return array<string, array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}>
     */
    private static function situations(array $posted, array $before): array
    {
        $list = [];
        foreach ($posted as $key => $s) {
            if (!\is_array($s) || !empty($s['remove'])) {
                continue;
            }
            $s['objectives'] = Analyst::strings($s['objectives'] ?? '', 4);
            if (null === $sit = Analyst::situation($s)) {
                continue;
            }
            $key = \is_string($key) && isset($before[$key]) ? $key : substr((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $sit['label']))), 0, 28);
            while (isset($list[$key])) {
                $key .= '_';
            }
            $list[$key] = $sit;
        }

        return [] !== $list ? $list : $before;
    }
}
