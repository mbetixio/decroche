<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une mission donnée par un manager : tant d'appels (d'une situation précise, ou de n'importe laquelle) avec au moins
 * telle note, avant telle date, pour un commercial ou pour toute l'équipe.
 */
#[ORM\Entity]
class Mission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Workspace $workspace,
        /** Le commercial visé ; null : toute l'équipe. */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public private(set) ?Seller $assignee,
        /** La situation à jouer (une clé du catalogue de l'espace) ; null : n'importe laquelle. */
        #[ORM\Column(length: 40, nullable: true)]
        public private(set) ?string $situation,
        #[ORM\Column]
        public private(set) int $calls,
        #[ORM\Column]
        public private(set) int $target,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        public private(set) ?\DateTimeImmutable $dueAt = null,
        #[ORM\Column(length: 200)]
        public private(set) string $note = '',
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Seller $createdBy = null,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->calls = max(1, min(20, $calls));
        $this->target = max(0, min(100, $target));
        $this->createdAt = $now ?? new \DateTimeImmutable();
    }

    public function concerns(Seller $seller): bool
    {
        return $seller->workspace === $this->workspace && (null === $this->assignee || $this->assignee === $seller);
    }

    /**
     * Les appels qui comptent pour la mission : terminés, notés au moins à la cible, joués depuis qu'elle existe (et
     * avant son échéance), sur la bonne situation ; les reprises d'un moment ne comptent pas.
     *
     * @param list<Training> $trainings ceux du commercial
     */
    public function progress(array $trainings): int
    {
        $due = $this->dueAt?->setTime(23, 59, 59);
        $count = 0;
        foreach ($trainings as $t) {
            if (Training::DONE === $t->status && null === $t->replayOf && null !== $t->score && $t->score >= $this->target
                && $t->startedAt >= $this->createdAt && (null === $due || $t->startedAt <= $due)
                && (null === $this->situation || $t->scenario->situation === $this->situation)) {
                ++$count;
            }
        }

        return min($count, $this->calls);
    }

    public function overdue(\DateTimeImmutable $now): bool
    {
        return null !== $this->dueAt && $now > $this->dueAt->setTime(23, 59, 59);
    }
}
