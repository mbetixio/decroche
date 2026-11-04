<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * L'espace d'une société cliente : qui elle est, ce qu'elle vend (sa fiche d'offre, tirée de son site puis corrigée),
 * ses clients types et les situations d'appel que ses commerciaux rencontrent. Tout l'entraînement s'y adapte.
 */
#[ORM\Entity]
class Workspace
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    /** Le site de la société, analysé pour écrire la fiche d'offre. */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $website = null;

    /** Ce que la société dit d'elle-même, en plus de son site. */
    #[ORM\Column(type: Types::TEXT)]
    public string $about = '';

    /** La fiche d'offre : ce que connaît le persona, ce que vérifie le coach, ce que les commerciaux doivent savoir. */
    #[ORM\Column(type: Types::TEXT)]
    public string $offer = '';

    /** @var list<string> Les secteurs ou types de clients, tirés au sort pour habiller les situations. */
    #[ORM\Column(type: Types::JSON)]
    public array $sectors = [];

    /**
     * @var array<string, array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $situations = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $analyzedAt = null;

    /**
     * @var array<string, array{label: string, hint: string}>|null la grille de notation de la société (null : celle de Sparring)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $criteria = null;

    public function __construct(
        #[ORM\Column(length: 120)]
        public string $name,
    ) {
        $this->name = trim($name);
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * L'espace est prêt quand la fiche d'offre et les situations existent : on peut jouer.
     */
    public function isReady(): bool
    {
        return '' !== trim($this->offer) && [] !== $this->situations;
    }

    /**
     * « d'Etixio », « de Paie Express » : le nom après « l'offre », « l'espace ».
     */
    public function of(): string
    {
        return (preg_match('/^[aeiouyhàâéèêëîïôöùûü]/iu', $this->name) ? 'd\'' : 'de ').$this->name;
    }

    /**
     * Les critères sur lesquels le coach note les commerciaux de la société.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public function scorecard(): array
    {
        return null !== $this->criteria && \count($this->criteria) >= 2 ? $this->criteria : \App\Training\Coach::DEFAULT_CRITERIA;
    }

    /**
     * @return array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}|null
     */
    public function situation(?string $key): ?array
    {
        return null === $key ? null : ($this->situations[$key] ?? null);
    }
}
