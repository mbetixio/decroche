<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une mise en situation : ce que le commercial sait (le contexte, l'objectif, le persona) et le brief caché du persona,
 * qui ne quitte jamais le serveur avant le débrief.
 */
#[ORM\Entity]
class Scenario
{
    public const CATEGORIES = [
        'decouverte' => 'Rendez-vous de découverte',
        'objection' => 'Traitement d\'objections',
        'negociation' => 'Négociation',
        'mecontent' => 'Client mécontent',
        'presse' => 'Prospect pressé',
        'closing' => 'Closing',
    ];

    public const DIFFICULTIES = [1 => 'Accessible', 2 => 'Exigeant', 3 => 'Coriace'];

    /** Les rubriques du brief caché, dans l'ordre où le débrief les dévoile. */
    public const BRIEF = [
        'budget' => 'Budget réel',
        'pain' => 'Vraie douleur',
        'decision' => 'Décideur et processus',
        'objections' => 'Objections',
        'yes' => 'Ce qui le ferait dire oui',
        'secret' => 'Ce qu\'il ne dit pas d\'emblée',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column]
    public bool $active = true;

    /** L'espace de la société à qui appartient le scénario. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?Workspace $workspace = null;

    /** La situation tirée au hasard (une clé des situations de l'espace) ; null pour un scénario de la bibliothèque. */
    #[ORM\Column(length: 30, nullable: true)]
    public ?string $situation = null;

    /** Ce que le commercial a sous les yeux avant l'appel : l'annonce, le message du formulaire, le mail du client… */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $material = null;

    /** @var list<string>|null Les objectifs vérifiables de l'appel, que le débrief déclare atteints ou non. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $objectives = null;

    /**
     * @param array{name: string, role: string, company: string, personality: string, gender?: string, tone?: string} $persona
     * @param array<string, string>                                                    $brief   rubrique de self::BRIEF => ce que le persona sait et cache
     */
    public function __construct(
        #[ORM\Column(length: 160)]
        public string $title,
        #[ORM\Column(length: 30)]
        public string $category,
        #[ORM\Column]
        public int $difficulty,
        #[ORM\Column]
        public int $minutes,
        /** Ce que le commercial sait en entrant : qui, pourquoi ce rendez-vous. */
        #[ORM\Column(type: Types::TEXT)]
        public string $context,
        /** Ce qu'il doit obtenir pour que le rendez-vous soit réussi. */
        #[ORM\Column(type: Types::TEXT)]
        public string $goal,
        #[ORM\Column(type: Types::JSON)]
        public array $persona,
        /** La première phrase du persona, qui ouvre la session. */
        #[ORM\Column(type: Types::TEXT)]
        public string $opening,
        #[ORM\Column(type: Types::JSON)]
        public array $brief,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Seller $author = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->workspace = $author?->workspace;
    }

    /**
     * Un scénario ne sort jamais de l'espace de sa société. Ceux de la bibliothèque (écrits par un manager, ou fournis)
     * sont à toute l'équipe ; celui qu'un commercial a tiré d'une fiche prospect reste à lui et aux managers.
     */
    public function visibleTo(Seller $seller): bool
    {
        if (null === $this->workspace || $this->workspace !== $seller->workspace) {
            return false;
        }

        return null === $this->author || $this->author->isManager() || $seller->isManager() || $this->author === $seller;
    }

    public function editableBy(Seller $seller): bool
    {
        return $this->visibleTo($seller) && ($seller->isManager() || (null !== $this->author && $this->author === $seller));
    }

    /**
     * @return array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}|null
     */
    public function situationInfo(): ?array
    {
        return $this->workspace?->situation($this->situation) ?? \App\Game\Situations::get($this->situation);
    }

    /**
     * @return list<string>
     */
    public function objectiveList(): array
    {
        return array_values(array_filter($this->objectives ?? [], static fn (string $o): bool => '' !== trim($o)));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function difficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? '';
    }

    /**
     * Ce que le commercial et son IA ont le droit de voir.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->categoryLabel(),
            'difficulty' => $this->difficultyLabel(),
            'minutes' => $this->minutes,
            'context' => $this->context,
            'goal' => $this->goal,
            'persona' => $this->persona,
        ] + (null !== $this->situation ? [
            'situation' => $this->situationInfo()['label'] ?? $this->situation,
            'call' => 'entrant' === ($this->situationInfo()['direction'] ?? '') ? 'Le client vous appelle' : 'Vous appelez',
            'material' => $this->material,
            'objectives' => $this->objectiveList(),
        ] : []);
    }
}
