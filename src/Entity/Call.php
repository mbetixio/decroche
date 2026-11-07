<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Enum\CallStatus;
use App\Enum\Speaker;
use App\Model\Line;
use App\Repository\CallRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A practice call: the conversation between a member and a persona, its clock, then its debrief.
 */
#[ORM\Entity(repositoryClass: CallRepository::class)]
#[ORM\Table(name: 'practice_call')]
#[ORM\Index(columns: ['status'])]
class Call
{
    /** Beyond this many seller lines, the call stops whatever happens: a meeting is not a novel. */
    public const int MAX_TURNS = 40;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(enumType: CallStatus::class, length: 20)]
    public private(set) CallStatus $status = CallStatus::Live;

    #[ORM\Column(enumType: CallEnd::class, length: 20, nullable: true)]
    public private(set) ?CallEnd $endedBy = null;

    /** @var list<array{role: string, content: string, at: string, voice?: array{ms: int}}> see {@see Line} */
    #[ORM\Column(type: Types::JSON)]
    private array $lines = [];

    /** @var list<string> the brief sections the persona gave away, because the seller asked the right question */
    #[ORM\Column(type: Types::JSON)]
    private array $revealed = [];

    /** @var array<string, mixed>|null the coach's debrief */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public private(set) ?array $debrief = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $score = null;

    /** A replay: the original call, resumed just before one of the seller's lines. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?Call $replayOf = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Member $member,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Scenario $scenario,
        \DateTimeImmutable $now,
    ) {
        $this->startedAt = self::local($now);
        $this->say(Speaker::Persona, $scenario->opening, $this->startedAt);
    }

    /**
     * Replays a moment: a new call that resumes this one just before its n-th line (one of the seller's), with the
     * time that was left then. This call is left untouched.
     */
    public static function replay(self $from, int $n, \DateTimeImmutable $now): self
    {
        $line = $from->lines()[$n] ?? null;
        if (null === $line || !$line->isSeller()) {
            throw new \InvalidArgumentException('call.error.replay_line');
        }
        $now = self::local($now);
        $elapsed = max(0, $line->at->getTimestamp() - $from->startedAt->getTimestamp());
        $replay = new self($from->member, $from->scenario, $now->modify(\sprintf('-%d seconds', $elapsed)));
        $shift = $now->getTimestamp() - $line->at->getTimestamp();
        $replay->lines = array_map(
            static fn (Line $l): array => new Line($l->speaker, $l->text, $l->at->modify(\sprintf('%+d seconds', $shift)), $l->voiceMs)->toArray(),
            \array_slice($from->lines(), 0, $n),
        );
        $replay->replayOf = $from->replayOf ?? $from;

        return $replay;
    }

    /**
     * @param int|null $voiceMs spoken into the microphone: how long it took, in milliseconds
     */
    public function say(Speaker $speaker, string $text, \DateTimeImmutable $at, ?int $voiceMs = null): void
    {
        $this->lines[] = new Line($speaker, $text, $at, null !== $voiceMs ? max(0, min(600_000, $voiceMs)) : null)->toArray();
    }

    /**
     * @return list<Line>
     */
    public function lines(): array
    {
        return array_map(Line::fromArray(...), $this->lines);
    }

    public function lastLine(): Line
    {
        $last = end($this->lines);

        return Line::fromArray(false !== $last ? $last : throw new \LogicException('A call always holds its opening line.'));
    }

    /**
     * The seller's line that contains this quote (to replay a moment from the debrief).
     */
    public function lineOf(string $quote): ?int
    {
        $norm = static fn (string $t): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(str_replace(['’', 'œ'], ["'", 'oe'], $t))));
        $needle = $norm($quote);
        if ('' === $needle) {
            return null;
        }
        foreach ($this->lines() as $i => $line) {
            if ($line->isSeller() && str_contains($norm($line->text), $needle)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether the seller spoke into the microphone (rather than typed).
     */
    public function isSpoken(): bool
    {
        foreach ($this->lines() as $line) {
            if (null !== $line->voiceMs) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notes that the persona gave a brief section away; false when it already had.
     */
    public function reveal(BriefSection $section): bool
    {
        if ($this->isRevealed($section)) {
            return false;
        }
        $this->revealed[] = $section->value;

        return true;
    }

    public function isRevealed(BriefSection $section): bool
    {
        return \in_array($section->value, $this->revealed, true);
    }

    public function revealedCount(): int
    {
        return \count($this->revealed);
    }

    public function isLive(): bool
    {
        return CallStatus::Live === $this->status;
    }

    public function isDone(): bool
    {
        return CallStatus::Done === $this->status;
    }

    public function isReplay(): bool
    {
        return null !== $this->replayOf;
    }

    public function deadline(): \DateTimeImmutable
    {
        return $this->startedAt->modify(\sprintf('+%d minutes', $this->scenario->minutes));
    }

    public function secondsLeft(\DateTimeImmutable $now): int
    {
        return max(0, $this->deadline()->getTimestamp() - $now->getTimestamp());
    }

    public function sellerTurns(): int
    {
        return \count(array_filter($this->lines(), static fn (Line $l): bool => $l->isSeller()));
    }

    /**
     * Hangs up: the call waits for its debrief, or is abandoned (never graded).
     */
    public function stop(CallEnd $by, \DateTimeImmutable $now, bool $abandoned = false): void
    {
        $this->status = $abandoned ? CallStatus::Abandoned : CallStatus::Debriefing;
        $this->endedBy = $by;
        $this->endedAt = self::local($now);
    }

    /**
     * Records the coach's debrief and the score.
     *
     * @param array<string, mixed> $debrief
     */
    public function close(array $debrief, int $score): void
    {
        $this->debrief = $debrief;
        $this->score = max(0, min(100, $score));
        $this->status = CallStatus::Done;
    }

    /**
     * The hidden brief, section by section, with what was found: only for the debrief.
     *
     * @return list<array{section: BriefSection, text: string, found: bool}>
     */
    public function briefSheet(): array
    {
        $sheet = [];
        foreach (BriefSection::cases() as $section) {
            if ('' !== $text = $this->scenario->brief($section)) {
                $sheet[] = ['section' => $section, 'text' => $text, 'found' => $this->isRevealed($section)];
            }
        }

        return $sheet;
    }

    public function durationMinutes(\DateTimeImmutable $now): int
    {
        return (int) ceil((($this->endedAt ?? $now)->getTimestamp() - $this->startedAt->getTimestamp()) / 60);
    }

    /**
     * A time in the application's time zone: the database stores times without one and reads them back in it.
     */
    private static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
