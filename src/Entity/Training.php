<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une session d'entraînement : la conversation entre un commercial et un persona, son horloge, puis son débrief.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['status'])]
class Training
{
    public const LIVE = 'live';
    public const DEBRIEFING = 'debriefing';
    public const DONE = 'done';
    public const ABANDONED = 'abandoned';

    public const SELLER = 'seller';
    public const PERSONA = 'persona';

    /** Au-delà, la conversation s'arrête quoi qu'il arrive : un rendez-vous n'est pas un roman. */
    public const MAX_TURNS = 40;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public private(set) \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(length: 20)]
    public private(set) string $status = self::LIVE;

    /** Pourquoi la session s'est arrêtée : le commercial, l'horloge, le persona qui met fin au rendez-vous. */
    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $endedBy = null;

    /** @var list<array{role: string, content: string, at: string, voice?: array{ms: int}}> */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $messages = [];

    /** @var list<string> Les rubriques du brief que le persona a lâchées, parce qu'on lui a posé la bonne question. */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $revealed = [];

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public private(set) ?array $debrief = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?int $score = null;

    /** Une reprise : l'appel d'origine, rejoué à partir d'une réplique du commercial. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public private(set) ?Training $replayOf = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Seller $seller,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public private(set) Scenario $scenario,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->startedAt = self::local($now ?? new \DateTimeImmutable());
        $this->say(self::PERSONA, $scenario->opening, $this->startedAt);
    }

    /**
     * Rejouer un moment : un nouvel appel qui reprend celui-ci juste avant la n-ième réplique (une réplique du
     * commercial), avec le temps qu'il restait à ce moment-là. L'appel d'origine n'est pas touché.
     */
    public static function replay(self $from, int $n, ?\DateTimeImmutable $now = null): self
    {
        if (self::SELLER !== ($from->messages[$n]['role'] ?? null)) {
            throw new \InvalidArgumentException('On ne rejoue qu\'à partir d\'une de vos répliques.');
        }
        $now = self::local($now ?? new \DateTimeImmutable());
        $at = self::local(new \DateTimeImmutable($from->messages[$n]['at']));
        $elapsed = max(0, $at->getTimestamp() - $from->startedAt->getTimestamp());
        $replay = new self($from->seller, $from->scenario, $now->modify('-'.$elapsed.' seconds'));
        $shift = $now->getTimestamp() - $at->getTimestamp();
        $replay->messages = array_map(static fn (array $m): array => ['at' => (new \DateTimeImmutable($m['at']))->modify(($shift >= 0 ? '+' : '').$shift.' seconds')->format(\DATE_ATOM)] + $m, \array_slice($from->messages, 0, $n));
        $replay->replayOf = $from->replayOf ?? $from;

        return $replay;
    }

    /**
     * La réplique du commercial qui contient cette citation (pour « rejouer ce moment » depuis le débrief).
     */
    public function lineOf(string $quote): ?int
    {
        $norm = static fn (string $t): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(str_replace(['’', 'œ'], ["'", 'oe'], $t))));
        $q = $norm($quote);
        if ('' === $q) {
            return null;
        }
        foreach ($this->messages as $i => $m) {
            if (self::SELLER === $m['role'] && str_contains($norm($m['content']), $q)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param int|null $voiceMs dite au micro : la durée de parole, en millisecondes (pour le débit)
     */
    public function say(string $role, string $content, ?\DateTimeImmutable $at = null, ?int $voiceMs = null): void
    {
        $this->messages[] = ['role' => $role, 'content' => $content, 'at' => ($at ?? new \DateTimeImmutable())->format(\DATE_ATOM)]
            + (null !== $voiceMs ? ['voice' => ['ms' => max(0, min(600000, $voiceMs))]] : []);
    }

    public function spoken(): bool
    {
        foreach ($this->messages as $m) {
            if (isset($m['voice'])) {
                return true;
            }
        }

        return false;
    }

    public function reveal(string $key): bool
    {
        if (!isset(Scenario::BRIEF[$key]) || \in_array($key, $this->revealed, true)) {
            return false;
        }
        $this->revealed[] = $key;

        return true;
    }

    public function isLive(): bool
    {
        return self::LIVE === $this->status;
    }

    public function deadline(): \DateTimeImmutable
    {
        return $this->startedAt->modify('+'.$this->scenario->minutes.' minutes');
    }

    public function secondsLeft(?\DateTimeImmutable $now = null): int
    {
        return max(0, $this->deadline()->getTimestamp() - ($now ?? new \DateTimeImmutable())->getTimestamp());
    }

    public function sellerTurns(): int
    {
        return \count(array_filter($this->messages, static fn (array $m): bool => self::SELLER === $m['role']));
    }

    /**
     * @param 'seller'|'clock'|'persona' $by
     */
    public function stop(string $by, bool $abandoned = false, ?\DateTimeImmutable $now = null): void
    {
        $this->status = $abandoned ? self::ABANDONED : self::DEBRIEFING;
        $this->endedBy = $by;
        $this->endedAt = self::local($now ?? new \DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $debrief
     */
    public function close(array $debrief, int $score): void
    {
        $this->debrief = $debrief;
        $this->score = $score;
        $this->status = self::DONE;
    }

    /**
     * Le brief caché, rubrique par rubrique, avec ce qui a été découvert : seulement pour le débrief.
     *
     * @return list<array{key: string, label: string, text: string, found: bool}>
     */
    public function briefSheet(): array
    {
        $out = [];
        foreach (Scenario::BRIEF as $key => $label) {
            $text = trim($this->scenario->brief[$key] ?? '');
            if ('' !== $text) {
                $out[] = ['key' => $key, 'label' => $label, 'text' => $text, 'found' => \in_array($key, $this->revealed, true)];
            }
        }

        return $out;
    }

    /**
     * Une heure dans le fuseau de l'application : la base la stocke sans fuseau et la relit dans celui-ci.
     */
    private static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    public function durationMinutes(): int
    {
        return (int) ceil((($this->endedAt ?? new \DateTimeImmutable())->getTimestamp() - $this->startedAt->getTimestamp()) / 60);
    }
}
