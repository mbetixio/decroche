<?php

namespace App\Command;

use App\Entity\Scenario;
use App\Entity\Seller;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Un sparring dans le terminal : pour essayer un scénario sans navigateur. Les répliques viennent du clavier, ou de
 * --line répété (une session scriptée de bout en bout).
 */
#[AsCommand('app:play', 'Joue une session de sparring dans le terminal, puis affiche le débrief.')]
final readonly class PlayCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private Trainer $trainer,
    ) {
    }

    /**
     * @param list<string> $line
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('L\'id du scénario')] int $scenario,
        #[Option('L\'e-mail du commercial')] string $seller = '',
        #[Option('Une réplique (répétable) : la session est jouée sans clavier')] array $line = [],
    ): int {
        $s = $this->em->find(Scenario::class, $scenario);
        $who = $this->em->getRepository(Seller::class)->findOneBy(['email' => mb_strtolower($seller)]);
        if (null === $s || null === $who) {
            $io->error('Scénario ou commercial introuvable.');

            return 1;
        }
        $training = $this->trainer->start($who, $s);
        $io->title($s->title);
        $io->text([$s->context, '<info>Objectif :</info> '.$s->goal, '']);
        $io->text('<comment>'.$s->persona['name'].' :</comment> '.$s->opening);
        $scripted = [] !== $line;
        while ($training->isLive()) {
            $text = $scripted ? array_shift($line) : $io->ask('Vous (vide pour terminer)', '');
            if (null === $text || '' === trim((string) $text)) {
                break;
            }
            if ($scripted) {
                $io->text('<info>Vous :</info> '.$text);
            }
            $start = microtime(true);
            $r = $this->trainer->say($training, (string) $text);
            $io->text(\sprintf('<comment>%s :</comment> %s <fg=gray>(%.1f s, %d s restantes, %d rubrique(s) découverte(s))</>', $s->persona['name'], $r['reply'], microtime(true) - $start, $r['secondsLeft'], $r['revealed']));
        }
        $io->section('Débrief');
        $start = microtime(true);
        $this->trainer->finish($training);
        $d = $training->debrief ?? [];
        $io->text(\sprintf('<info>%d/100</info> (%.1f s) — %s', $training->score, microtime(true) - $start, $d['summary'] ?? ''));
        $io->table(['Critère', 'Note', 'Commentaire'], array_map(static fn (array $c): array => [$c['label'], $c['score'].'/10', $c['comment']], $d['criteria'] ?? []));
        $io->listing(array_map(static fn (array $b): string => ($b['found'] ? '✅ ' : '❌ ').$b['label'].' : '.$b['text'], $training->briefSheet()));
        foreach ($d['best'] ?? [] as $q) {
            $io->text('👍 « '.$q['quote'].' » — '.$q['why']);
        }
        foreach ($d['worst'] ?? [] as $q) {
            $io->text('👎 « '.$q['quote'].' » — '.$q['why'].' 👉 '.$q['better']);
        }
        $io->listing($d['tips'] ?? []);

        return 0;
    }
}
