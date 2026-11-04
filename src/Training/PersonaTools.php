<?php

namespace App\Training;

use App\Entity\Scenario;
use App\Entity\Training;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * Les gestes du persona pendant la conversation, que le commercial ne voit pas : noter qu'il vient de lâcher une
 * rubrique de son brief (le débrief saura ce qui a été découvert), ou mettre fin au rendez-vous.
 */
#[AsTool('devoiler', 'À appeler dès que tu viens de révéler, dans ta réponse, une rubrique de ton brief caché parce que le commercial t\'a posé la bonne question. rubrique : budget, pain, decision, objections, yes ou secret.', method: 'reveal')]
#[AsTool('mettre_fin', 'À appeler seulement si tu mets fin au rendez-vous maintenant (le commercial est irrespectueux, te fait perdre ton temps, ou vous avez convenu de la suite et vous vous quittez).', method: 'end')]
final class PersonaTools
{
    public bool $ended = false;

    public function __construct(
        private readonly Training $training,
    ) {
    }

    /**
     * @param string $rubrique budget, pain, decision, objections, yes ou secret
     */
    public function reveal(string $rubrique): string
    {
        $key = strtolower(trim($rubrique));
        if (!isset(Scenario::BRIEF[$key])) {
            return 'Rubrique inconnue : budget, pain, decision, objections, yes ou secret.';
        }
        $this->training->reveal($key);

        return 'Noté. Continue ta réponse, toujours dans ton rôle.';
    }

    /**
     * @param string $raison en quelques mots
     */
    public function end(string $raison = ''): string
    {
        $this->ended = true;

        return 'Le rendez-vous s\'arrête après ta réponse : prends congé, dans ton rôle.';
    }
}
