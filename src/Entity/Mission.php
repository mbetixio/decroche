<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A goal a manager sets: so many calls (of one situation, or of any) scored at least so much, before a date, for one
 * member or for the whole team.
 */
#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column]
    public private(set) int $calls;

    #[ORM\Column]
    public private(set) int $target;

    public function __construct(
        /** The member it is for; null: the whole team. */
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        public private(set) ?Member $assignee,
        /** The situation to play (a key of the company's catalogue); null: any. */
        #[ORM\Column(length: 40, nullable: true)]
        public private(set) ?string $situation,
        int $calls,
        int $target,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        public private(set) ?\DateTimeImmutable $dueAt,
        #[ORM\Column(length: 200)]
        public private(set) string $note,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        public private(set) ?Member $createdBy,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        public private(set) \DateTimeImmutable $createdAt,
    ) {
        $this->calls = max(1, min(20, $calls));
        $this->target = max(0, min(100, $target));
    }

    public function concerns(Member $member): bool
    {
        return null === $this->assignee || $this->assignee === $member;
    }

    /**
     * The calls that count: debriefed, scored at least the target, played since the mission exists (and before its
     * due date), on the right situation. Replays of a moment do not count.
     *
     * @param iterable<Call> $calls the member's
     */
    public function progress(iterable $calls): int
    {
        $due = $this->dueAt?->setTime(23, 59, 59);
        $count = 0;
        foreach ($calls as $call) {
            if ($call->isDone() && !$call->isReplay() && ($call->score ?? -1) >= $this->target
                && $call->startedAt >= $this->createdAt && (null === $due || $call->startedAt <= $due)
                && (null === $this->situation || $call->scenario->situation()?->key === $this->situation)) {
                ++$count;
            }
        }

        return min($count, $this->calls);
    }

    public function isOverdue(\DateTimeImmutable $now): bool
    {
        return null !== $this->dueAt && $now > $this->dueAt->setTime(23, 59, 59);
    }
}
