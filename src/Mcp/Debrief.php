<?php

namespace App\Mcp;

use App\Entity\Training;
use App\Training\Trainer;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;

/**
 * Le débrief en MCP App : la note, les critères, le brief caché dévoilé, les répliques citées, les conseils.
 */
#[AsMcpApp(
    uri: 'ui://sparring/debrief',
    name: 'debrief',
    title: 'Afficher le débrief',
    description: 'Affiche le débrief noté d\'une session terminée (la dernière par défaut) : la note sur 100, les critères de la grille de la société, le brief caché dévoilé avec ce qui a été découvert ou manqué, les meilleures et pires répliques, trois conseils. Si la session vient de s\'arrêter, le débrief est rédigé d\'abord (environ une minute).',
    template: 'mcp/debrief.html.twig',
    prefersBorder: false,
    cspResource: ['https://fonts.googleapis.com', 'https://fonts.gstatic.com', '%env(DEFAULT_URI)%'],
)]
final readonly class Debrief
{
    public function __construct(
        private Me $me,
        private Trainer $trainer,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function render(int $session = 0): array
    {
        $training = $this->ready($session);

        return Training::DONE === $training->status ? Sparring::summary($training) : ['session' => $training->id, 'status' => $training->status];
    }

    /**
     * @return array<string, mixed>
     */
    #[AsMcpAppTool(name: 'debrief_view', title: 'Afficher le débrief', description: 'Rend le débrief.', template: 'mcp/_debrief.html.twig', appOnly: true)]
    public function view(int $session = 0): array
    {
        return ['t' => $this->ready($session)];
    }

    private function ready(int $session): Training
    {
        $training = $this->me->training($session);
        if (Training::DEBRIEFING === $training->status) {
            $this->trainer->finish($training);
        }

        return $training;
    }
}
