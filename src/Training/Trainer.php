<?php

namespace App\Training;

use App\Entity\Scenario;
use App\Entity\Seller;
use App\Entity\Training;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Le déroulé d'une session : on la lance, on parle au persona, l'horloge tourne, on termine (ou on abandonne), le coach
 * débriefe. Le web et le MCP passent tous les deux par ici.
 */
final readonly class Trainer
{
    public function __construct(
        private EntityManagerInterface $em,
        private Persona $persona,
        private Coach $coach,
        private ClockInterface $clock,
    ) {
    }

    public function start(Seller $seller, Scenario $scenario): Training
    {
        if (!$scenario->active) {
            throw new \InvalidArgumentException('Ce scénario est archivé.');
        }
        $training = new Training($seller, $scenario, $this->clock->now());
        $this->em->persist($training);
        $this->em->flush();

        return $training;
    }

    /**
     * Rejouer un moment : un nouvel appel repris juste avant la n-ième réplique du commercial (la session en cours,
     * s'il y en a une, est abandonnée).
     */
    public function replay(Training $from, int $n): Training
    {
        $current = $this->current($from->seller);
        if (null !== $current && $current->isLive()) {
            $this->abandon($current);
        }
        $replay = Training::replay($from, $n, $this->clock->now());
        $this->em->persist($replay);
        $this->em->flush();

        return $replay;
    }

    /**
     * Le commercial parle, le persona répond. L'horloge peut avoir sonné avant : la session s'arrête alors sans réponse.
     *
     * @param int|null $voiceMs la réplique a été dite au micro, en tant de millisecondes
     *
     * @return array{reply: ?string, ended: bool, endedBy: ?string, secondsLeft: int, revealed: int}
     */
    public function say(Training $training, string $text, ?int $voiceMs = null): array
    {
        $stream = $this->stream($training, $text, $voiceMs);
        foreach ($stream as $ignored) {
        }

        return $stream->getReturn();
    }

    /**
     * La même chose au fil de l'eau : la réponse du persona arrive morceau par morceau, le bilan à la fin.
     *
     * @return \Generator<int, string, mixed, array{reply: ?string, ended: bool, endedBy: ?string, secondsLeft: int, revealed: int}>
     */
    public function stream(Training $training, string $text, ?int $voiceMs = null, ?string $note = null): \Generator
    {
        $text = trim($text);
        if ('' === $text) {
            throw new \InvalidArgumentException('Votre message est vide.');
        }
        if (mb_strlen($text) > 2000) {
            throw new \InvalidArgumentException('Votre message est trop long : en rendez-vous, on parle, on ne lit pas un mémoire.');
        }
        $this->expire($training);
        if (!$training->isLive()) {
            throw new \InvalidArgumentException('Cette session est terminée.');
        }
        $training->say(Training::SELLER, $text, $this->clock->now(), $voiceMs);
        $this->em->flush();
        $answer = yield from $this->persona->stream($training, $this->clock->now(), $note);
        $training->say(Training::PERSONA, $answer['text'], $this->clock->now());
        if ($answer['ended']) {
            $training->stop('persona', now: $this->clock->now());
        } elseif ($training->sellerTurns() >= Training::MAX_TURNS) {
            $training->stop('clock', now: $this->clock->now());
        } else {
            $this->expire($training);
        }
        $this->em->flush();

        return ['reply' => $answer['text'], 'ended' => !$training->isLive(), 'endedBy' => $training->endedBy, 'secondsLeft' => $training->secondsLeft($this->clock->now()), 'revealed' => \count($training->revealed)];
    }

    /**
     * L'horloge : une session vivante dont le temps est écoulé s'arrête (le débrief reste à faire).
     */
    public function expire(Training $training): bool
    {
        if ($training->isLive() && 0 === $training->secondsLeft($this->clock->now())) {
            $training->stop('clock', now: $training->deadline());
            $this->em->flush();

            return true;
        }

        return false;
    }

    /**
     * Termine la session et rédige son débrief (s'il n'a pas pu l'être la première fois, on recommence).
     */
    public function finish(Training $training): Training
    {
        $this->expire($training);
        if ($training->isLive()) {
            $training->stop('seller', now: $this->clock->now());
            $this->em->flush();
        }
        if (Training::DEBRIEFING === $training->status) {
            $result = $this->coach->debrief($training);
            $result['debrief']['speech'] = Speech::analyze($training);
            $training->close($result['debrief'], $result['score']);
            $this->em->flush();
        }

        return $training;
    }

    public function abandon(Training $training): Training
    {
        if (!$training->isLive()) {
            throw new \InvalidArgumentException('Cette session est déjà terminée.');
        }
        $training->stop('seller', abandoned: true, now: $this->clock->now());
        $this->em->flush();

        return $training;
    }

    /**
     * La session en cours d'un commercial, s'il en a une.
     */
    public function current(Seller $seller): ?Training
    {
        $training = $this->em->getRepository(Training::class)->findOneBy(['seller' => $seller, 'status' => [Training::LIVE, Training::DEBRIEFING]], ['startedAt' => 'DESC']);
        if (null !== $training) {
            $this->expire($training);
        }

        return $training;
    }
}
