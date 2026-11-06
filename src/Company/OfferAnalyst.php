<?php

declare(strict_types=1);

namespace App\Company;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Entity\Company;
use App\Model\Situation;
use App\Scenario\SituationCanvas;
use Psr\Clock\ClockInterface;

/**
 * Analyses the company: its website and what it says about itself become its offer sheet and typical clients (a first
 * pass of the model), then its catalogue of call situations (a second pass), all to be corrected by hand.
 */
final readonly class OfferAnalyst
{
    public function __construct(
        private LanguageModel $model,
        private SiteReader $site,
        private SituationCanvas $canvas,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The whole analysis (a minute or two).
     *
     * @throws \InvalidArgumentException when there is neither a readable site nor a description to work from
     * @throws ModelException            when the model's answer is unusable
     */
    public function analyze(Company $company): void
    {
        $site = null !== $company->website && '' !== $company->website ? $this->site->read($company->website) : '';
        if ('' === trim($site) && mb_strlen(trim($company->about)) < 30) {
            throw new \InvalidArgumentException('Donnez l\'adresse d\'un site lisible, ou décrivez votre entreprise en quelques phrases.');
        }
        $this->offer($company, $site);
        $this->situations($company);
        $company->analyzedAt = $this->clock->now();
    }

    /**
     * Drafts the offer sheet and the typical client sectors.
     *
     * @throws ModelException
     */
    public function offer(Company $company, string $site): void
    {
        $system = <<<'PROMPT'
            Tu prépares un outil d'entraînement commercial : des commerciaux vont s'entraîner au téléphone contre des clients
            simulés. Pour que les situations soient crédibles, tu rédiges la fiche d'offre de leur société, à partir de son site
            et de ce qu'elle dit d'elle-même. Cette fiche servira au client simulé (ce qu'il peut savoir et objecter), au coach
            (qui pénalise toute promesse hors offre) et aux commerciaux.
            N'écris que ce que les sources disent ou impliquent clairement ; quand une information manque (prix, délais…),
            écris « à préciser » plutôt que d'inventer. Aucun tarif inventé.
            Réponds uniquement avec un objet JSON, en français :
            {"name": "le nom de la société",
             "offer": "la fiche, en texte simple avec ces rubriques, chacune sur son paragraphe :\nQui : …\n\nLes offres : (une ligne par offre, commençant par « - »)\n\nLe cadre : délais, prix et façon de chiffrer, démarche commerciale (premier rendez-vous, démo, devis…), engagements\n\nRéférences et preuves : …\n\nCe que la société ne propose pas, ou que rien ne garantit : …\n\nLes objections classiques des prospects : (une ligne par objection, entre guillemets, comme un client la dirait)\n\nLes arguments forts : …",
             "sectors": ["10 à 15 secteurs ou types de clients que la société sert ou vise (en minuscules, ex. « e-commerce », « cabinets d'avocats », « collectivités »)"]}
            PROMPT;
        $user = 'Ce que la société dit d\'elle-même : '.('' !== trim($company->about) ? trim($company->about) : '(rien)')
            ."\n\nSon nom déclaré : {$company->name}"
            ."\n\nSon site".('' !== $site ? " :\n{$site}" : ' : (pas de site lisible)');

        $data = $this->model->json($system, $user, 0.3);
        $offer = \is_string($data['offer'] ?? null) ? trim($data['offer']) : '';
        if (mb_strlen($offer) < 80) {
            throw new ModelException('The offer sheet the model wrote is too short.');
        }
        $company->offer = $offer;
        $company->setSectors(Situation::strings($data['sectors'] ?? []));
    }

    /**
     * Writes the catalogue of call situations from the offer sheet: the common canvas adapted to the company's
     * business, plus its own.
     *
     * @throws ModelException
     */
    public function situations(Company $company): void
    {
        $canvas = json_encode(array_values(array_map(static fn (Situation $s): array => ['label' => $s->label, 'family' => $s->family->value, 'direction' => $s->direction->value, 'pitch' => $s->pitch], $this->canvas->situations())), \JSON_UNESCAPED_UNICODE);
        $system = <<<PROMPT
            Tu prépares un jeu d'entraînement commercial : à chaque partie, un commercial tire une situation d'appel au hasard
            (un appel qu'il passe ou qu'il reçoit) et la joue contre un client simulé. Écris le catalogue des situations de
            cette société, d'après sa fiche d'offre : 14 à 18 situations variées et réalistes pour SON métier, qui couvrent
            la prospection, la relation client et la négociation. Reprends et adapte les situations du canevas commun qui
            ont du sens pour elle (en réécrivant le pitch avec ses offres, ses clients, ses objections), et ajoute 4 à 6
            situations propres à son métier.
            Le canevas commun : {$canvas}
            Réponds uniquement avec un objet JSON, en français :
            {"situations": [{"key": "identifiant_court_en_snake_case", "label": "titre court", "emoji": "un emoji",
              "family": "prospection, client ou negociation", "direction": "sortant (le commercial appelle) ou entrant (on l'appelle)",
              "material": "le document que le commercial a sous les yeux avant l'appel (« Le message du formulaire », « La fiche client »…)",
              "pitch": "3 ou 4 phrases : qui est en face, ce qu'il veut, ce qui coince, ce qui se cache",
              "objectives": ["trois objectifs concrets et vérifiables pour l'appel"]}]}
            PROMPT;

        $data = $this->model->json($system, "La société : {$company->name}\n\nSa fiche d'offre :\n{$company->offer}", 0.7);
        $situations = [];
        foreach (\is_array($data['situations'] ?? null) ? $data['situations'] : [] as $s) {
            if (\is_array($s) && null !== $situation = Situation::fromArray(\is_string($s['key'] ?? null) ? $s['key'] : '', $s)) {
                $situations[] = $situation;
            }
        }
        if (\count($situations) < 5) {
            throw new ModelException('The catalogue of situations the model wrote is too short.');
        }
        $company->setSituations($situations);
    }
}
