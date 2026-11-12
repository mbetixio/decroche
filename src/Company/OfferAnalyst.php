<?php

declare(strict_types=1);

namespace App\Company;

use App\Ai\LanguageModel;
use App\Ai\ModelException;
use App\Ai\Output\OfferSheet;
use App\Ai\Output\SituationCatalogue;
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
     * The whole analysis (a minute or two), told step by step.
     *
     * @param \Closure|null $step told each step as it starts
     *
     * @throws \InvalidArgumentException when there is neither a readable site nor a description to work from
     * @throws ModelException            when the model's answer is unusable
     */
    public function analyze(Company $company, ?\Closure $step = null): void
    {
        $step ??= static function (): void {};
        $step('analysis.site', ['%site%' => $company->website ?? '—']);
        $site = null !== $company->website && '' !== $company->website ? $this->site->read($company->website) : '';
        if ('' === trim($site) && mb_strlen(trim($company->about)) < 30) {
            throw new \InvalidArgumentException('company.error.nothing_to_analyze');
        }
        $step('analysis.offer');
        $this->offer($company, $site);
        $step('analysis.situations');
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
        $language = $company->language->englishName();
        $system = <<<PROMPT
            You are preparing a sales training tool: sellers will practise on the phone against simulated clients. For the
            situations to be believable, you write their company's offer sheet, from its website and what it says about itself.
            This sheet will be used by the simulated client (what they may know and object), by the coach (who penalizes any
            promise outside the offer) and by the sellers.
            Only write what the sources say or clearly imply; when a piece of information is missing (prices, lead times…),
            write "to be specified" (in {$language}) rather than inventing. No invented prices.
            Answer only with a JSON object; every text value is written in {$language}:
            {"name": "the company's name",
             "offer": "the sheet, in plain text with these sections, each in its own paragraph, their titles translated into {$language}:\nWho: …\n\nThe offers: (one line per offer, starting with \"- \")\n\nThe terms: lead times, prices and how they are quoted, sales process (first meeting, demo, quote…), commitments\n\nReferences and proof: …\n\nWhat the company does not offer, or what nothing guarantees: …\n\nProspects' classic objections: (one line per objection, in quotes, as a client would say it)\n\nThe strong arguments: …",
             "sectors": ["10 to 15 sectors or types of clients the company serves or targets (lowercase)"]}
            PROMPT;
        $user = 'What the company says about itself: '.('' !== trim($company->about) ? trim($company->about) : '(nothing)')
            ."\n\nIts declared name: {$company->name}"
            ."\n\nIts website".('' !== $site ? ":\n{$site}" : ': (no readable website)');

        $data = $this->model->structured($system, $user, OfferSheet::class, 0.3)->toArray();
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
        $canvas = json_encode(array_values(array_map(static fn (Situation $s): array => ['label' => $s->label, 'family' => $s->family->value, 'direction' => $s->direction->value, 'pitch' => $s->pitch], $this->canvas->situations($company->language))), \JSON_UNESCAPED_UNICODE);
        $language = $company->language->englishName();
        $system = <<<PROMPT
            You are preparing a sales training game: in each round, a seller draws a phone call situation at random (a call
            they make or receive) and plays it against a simulated client. Write this company's catalogue of situations, from
            its offer sheet: 14 to 18 varied, realistic situations for ITS business, covering prospecting, client relations and
            negotiation. Take and adapt the situations of the common canvas that make sense for it (rewriting the pitch with
            its offers, its clients, its objections), and add 4 to 6 situations specific to its business.
            The common canvas: {$canvas}
            Answer only with a JSON object; every text value is written in {$language}:
            {"situations": [{"key": "short_identifier_in_snake_case", "label": "short title", "emoji": "one emoji",
              "family": "prospecting, client or negotiation", "direction": "outgoing (the seller calls) or incoming (the seller is called)",
              "material": "the document the seller has in front of them before the call (the form message, the client file…)",
              "pitch": "3 or 4 sentences: who is on the other end, what they want, what is stuck, what is hidden",
              "objectives": ["three concrete, checkable objectives for the call"]}]}
            PROMPT;

        $data = $this->model->structured($system, "The company: {$company->name}\n\nIts offer sheet:\n{$company->offer}", SituationCatalogue::class, 0.7)->toArray();
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
