<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Language;
use App\Model\Criterion;
use App\Model\Situation;
use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The company this Décroche instance trains: who it is, what it sells (its offer sheet, drafted from its website then
 * corrected), its typical clients, the call situations its sellers run into, and its scorecard. There is one per
 * instance; the onboarding fills it in on first boot.
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(length: 120)]
    public string $name = '';

    /** The working language: the interface, the personas, the coach and the analysis all speak it. */
    #[ORM\Column(enumType: Language::class, length: 5)]
    public Language $language = Language::French;

    /** The company's website, read by the analysis. */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $website = null;

    /** What the company says about itself, on top of its website. */
    #[ORM\Column(type: Types::TEXT)]
    public string $about = '';

    /** The offer sheet: what personas know, what the coach checks, what sellers must know. */
    #[ORM\Column(type: Types::TEXT)]
    public string $offer = '';

    /** @var list<string> typical client sectors, drawn at random to dress up situations */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $sectors = [];

    /** @var array<string, array<string, mixed>> the call situations, by key (see {@see Situation}) */
    #[ORM\Column(type: Types::JSON)]
    private array $situations = [];

    /** @var array<string, array{label: string, hint: string}>|null the scorecard (null: Décroche's own) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $criteria = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $analyzedAt = null;

    /** The furthest onboarding step reached. */
    #[ORM\Column(enumType: OnboardingStep::class, length: 20)]
    public private(set) OnboardingStep $step = OnboardingStep::Welcome;

    /** When the onboarding was completed; until then, the instance only shows the onboarding. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $onboardedAt = null;

    /**
     * @param list<string> $sectors
     */
    public function setSectors(array $sectors): void
    {
        $this->sectors = Situation::strings($sectors, 30);
    }

    /**
     * @return array<string, Situation>
     */
    public function situations(): array
    {
        $list = [];
        foreach ($this->situations as $key => $data) {
            if (null !== $situation = Situation::fromArray((string) $key, $data)) {
                $list[$situation->key] = $situation;
            }
        }

        return $list;
    }

    public function situation(?string $key): ?Situation
    {
        return null === $key ? null : ($this->situations()[$key] ?? null);
    }

    /**
     * Replaces the catalogue, keys made unique.
     *
     * @param iterable<Situation> $situations
     */
    public function setSituations(iterable $situations): void
    {
        $this->situations = [];
        foreach ($situations as $situation) {
            // Two situations with the same key: the second becomes "key_2" (a key the slug keeps as it is).
            $key = $situation->key;
            for ($n = 2; isset($this->situations[$key]); ++$n) {
                $key = $situation->key.'_'.$n;
            }
            $this->situations[$key] = ['key' => $key] + $situation->toArray();
        }
    }

    /**
     * Adds the situations the catalogue lacks, without touching the ones already there.
     *
     * @param iterable<Situation> $situations
     */
    public function addMissingSituations(iterable $situations): void
    {
        foreach ($situations as $situation) {
            $this->situations[$situation->key] ??= $situation->toArray();
        }
    }

    /**
     * The criteria the coach grades on: the company's own, or Décroche's.
     *
     * @return array<string, Criterion>
     */
    public function scorecard(): array
    {
        if (null === $this->criteria || \count($this->criteria) < 2) {
            return Criterion::defaults($this->language);
        }
        $list = [];
        foreach ($this->criteria as $key => $c) {
            $list[$key] = new Criterion($key, $c['label'], $c['hint']);
        }

        return $list;
    }

    /**
     * @param list<Criterion> $criteria two to eight; fewer brings back Décroche's own scorecard
     */
    public function setScorecard(array $criteria): void
    {
        $grid = [];
        foreach (\array_slice($criteria, 0, 8) as $criterion) {
            $grid[$criterion->key] = $criterion->toArray();
        }
        $this->criteria = \count($grid) >= 2 ? $grid : null;
    }

    public function hasOwnScorecard(): bool
    {
        return null !== $this->criteria;
    }

    /**
     * Ready to play: the offer sheet and the situations exist.
     */
    public function isReady(): bool
    {
        return '' !== trim($this->offer) && [] !== $this->situations;
    }

    public function isOnboarded(): bool
    {
        return null !== $this->onboardedAt;
    }

    /**
     * Records that the onboarding reached this step (it never goes back).
     */
    public function reach(OnboardingStep $step): void
    {
        if ($step->index() > $this->step->index()) {
            $this->step = $step;
        }
    }

    /**
     * Whether the onboarding already reached this step (it can be revisited).
     */
    public function hasReached(OnboardingStep $step): bool
    {
        return $step->index() <= $this->step->index();
    }

    public function completeOnboarding(\DateTimeImmutable $now): void
    {
        $this->step = OnboardingStep::Done;
        $this->onboardedAt = $now;
    }
}
