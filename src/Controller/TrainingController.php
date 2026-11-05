<?php

namespace App\Controller;

use App\Entity\Training;
use App\Game\Level;
use App\Game\Situations;
use App\Training\Persona;
use App\Training\Trainer;
use App\Voice\EdgeVoice;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingController extends Controller
{
    public function __construct(
        private readonly Trainer $trainer,
        private readonly EdgeVoice $voice,
    ) {
    }

    /**
     * La session : le chat tant qu'elle vit, le débrief ensuite. Le manager voit les sessions de toute l'équipe.
     */
    #[Route('/sessions/{id<\d+>}', name: 'training')]
    public function show(Training $training): Response
    {
        $this->see($training, true);
        $this->trainer->expire($training);

        return $this->render('training/show.html.twig', ['t' => $training, 'mine' => $training->seller === $this->me(), 'xp' => Level::xp($training), 'edge' => $this->voice->available() ? self::voiceOf($training) : null]);
    }

    #[Route('/sessions/{id<\d+>}/message', name: 'training_say', methods: ['POST'])]
    public function say(Training $training, Request $request): Response
    {
        $this->see($training);
        $this->checkToken('training'.$training->id, $request->headers->get('X-CSRF-Token'));
        set_time_limit(180);
        $body = json_decode($request->getContent(), true);
        $message = \is_array($body) ? (string) ($body['message'] ?? '') : '';
        $voice = \is_array($body) && is_numeric($body['voiceMs'] ?? null) ? (int) $body['voiceMs'] : null;
        if (str_contains((string) $request->headers->get('Accept'), 'ndjson')) {

            $note = \is_array($body) && true === ($body['interrupted'] ?? null) ? Persona::INTERRUPTED : null;

            return $this->streamed($training, $request, $message, $voice, $note);
        }
        try {
            $r = $this->trainer->say($training, $message, $voice);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'ended' => !$training->isLive()], 400);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le persona n\'a pas pu répondre (le modèle est injoignable) : renvoyez votre message.'], 502);
        }

        // La voix de la réplique, générée à la demande (voix neuronale d'Edge) ; le navigateur retombe sur la sienne sinon.
        $r['audio'] = $this->voice->available() ? $this->generateUrl('training_voice', ['id' => $training->id, 'n' => \count($training->messages) - 1]) : null;

        return new JsonResponse($r);
    }

    /**
     * La réponse en flux (une ligne JSON par évènement) : chaque phrase part dès qu'elle est écrite, avec sa voix, générée
     * pendant que le modèle écrit la suivante ; le bilan du tour vient à la fin.
     */
    private function streamed(Training $training, Request $request, string $message, ?int $voiceMs, ?string $note = null): Response
    {
        $turn = $this->trainer->stream($training, $message, $voiceMs, $note);
        try {
            $turn->current();
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'ended' => !$training->isLive()], 400);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le persona n\'a pas pu répondre (le modèle est injoignable) : renvoyez votre message.'], 502);
        }
        // La session est relâchée : le reste du site répond pendant que le persona parle.
        if ($request->hasSession()) {
            $request->getSession()->save();
        }
        $voice = $this->voice->available() && '1' !== $request->query->get('browserVoice') ? self::voiceOf($training) : null;
        $prosody = self::prosodyOf($training);

        return new StreamedResponse(function () use ($turn, $voice, $prosody): void {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $emit = static function (array $event): void {
                echo json_encode($event, \JSON_UNESCAPED_UNICODE)."\n";
                flush();
            };
            /** @var list<array{0: string, 1: ?array{file: string, part: string, process: ?\Symfony\Component\Process\Process}}> $queue */
            $queue = [];
            $push = function (string $sentence) use (&$queue, $voice, $prosody): void {
                $job = null;
                if (null !== $voice) {
                    try {
                        $job = $this->voice->begin($sentence, $voice, ...$prosody);
                    } catch (\Throwable) {
                    }
                }
                $queue[] = [$sentence, $job];
            };
            // Les phrases sortent dans l'ordre, chacune quand sa voix est prête (ou tout de suite, sans voix).
            $drain = function (bool $wait) use (&$queue, $emit): void {
                while ([] !== $queue) {
                    [$text, $job] = $queue[0];
                    if (null !== $job && !EdgeVoice::done($job)) {
                        if (!$wait) {
                            return;
                        }
                        $job['process']?->wait();
                    }
                    array_shift($queue);
                    $audio = null !== $job ? $this->voice->result($job) : null;
                    $emit(['t' => 'say', 'text' => $text, 'audio' => null !== $audio ? base64_encode($audio) : null]);
                }
            };
            $pending = '';
            $started = false;
            try {
                foreach ($turn as $chunk) {
                    $pending .= $chunk;
                    // Une phrase est finie quand sa ponctuation est suivie d'un blanc.
                    while (preg_match('/^(.+?[.!?…]+[»")]*)\s+(.*)$/su', $pending, $m)
                        // Le tout premier morceau part dès une virgule, pour que la voix démarre au plus tôt.
                        || (!$started && preg_match('/^(.{25,}?[,;:])\s+(.*)$/su', $pending, $m))) {
                        $push(trim($m[1]));
                        $pending = $m[2];
                        $started = true;
                    }
                    $drain(false);
                }
                if ('' !== trim($pending)) {
                    $push(trim($pending));
                }
                $drain(true);
                $emit(['t' => 'done'] + $turn->getReturn());
            } catch (\Throwable) {
                $drain(true);
                $emit(['t' => 'error', 'error' => 'La ligne a coupé : le persona n\'a pas pu finir sa réponse.']);
            }
        }, 200, ['Content-Type' => 'application/x-ndjson; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }

    /**
     * Le MP3 d'une réplique du persona, dans sa voix (toujours la même pour lui).
     */
    #[Route('/sessions/{id<\d+>}/voix/{n<\d+>}', name: 'training_voice', methods: ['GET'])]
    public function voice(Training $training, int $n): Response
    {
        $this->see($training, true);
        $message = $training->messages[$n] ?? null;
        if (null === $message || Training::PERSONA !== $message['role']) {
            throw $this->createNotFoundException();
        }
        set_time_limit(60);
        try {
            $audio = $this->voice->speak($message['content'], self::voiceOf($training), ...self::prosodyOf($training));
        } catch (\Throwable) {
            return new Response('', 503);
        }

        return new Response($audio, 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Le débit et la hauteur de la voix, selon le ton du persona (un client fâché parle plus vite et plus haut).
     *
     * @return array{rate: string, pitch: string}
     */
    public static function prosodyOf(Training $training): array
    {
        $tone = Situations::TONES[$training->scenario->persona['tone'] ?? ''] ?? null;

        return ['rate' => $tone['rate'] ?? EdgeVoice::RATE, 'pitch' => $tone['pitch'] ?? '+0Hz'];
    }

    public static function voiceOf(Training $training): string
    {
        return EdgeVoice::voiceFor($training->scenario->persona, 'quebec' === $training->scenario->situation ? 'fr-CA' : 'fr-FR');
    }

    #[Route('/sessions/{id<\d+>}/terminer', name: 'training_finish', methods: ['POST'])]
    public function finish(Training $training, Request $request): Response
    {
        $this->see($training);
        $this->checkToken('training'.$training->id, $request->request->getString('_token'));
        set_time_limit(300);
        try {
            $this->trainer->finish($training);
        } catch (\Throwable) {
            $this->addFlash('error', 'Le débrief n\'a pas pu être rédigé (le modèle est injoignable) : réessayez.');
        }

        return $this->redirectToRoute('training', ['id' => $training->id]);
    }

    /**
     * Rejouer à partir d'une de ses répliques : l'appel reprend juste avant, pour essayer autrement.
     */
    #[Route('/sessions/{id<\d+>}/rejouer/{n<\d+>}', name: 'training_replay', methods: ['POST'])]
    public function replay(Training $training, int $n, Request $request): Response
    {
        $this->see($training);
        $this->checkToken('replay'.$training->id, $request->request->getString('_token'));
        if ($training->isLive()) {
            throw $this->createAccessDeniedException('Terminez d\'abord cet appel.');
        }
        try {
            $replay = $this->trainer->replay($training, $n);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('training', ['id' => $training->id]);
        }

        return $this->redirectToRoute('training', ['id' => $replay->id]);
    }

    #[Route('/sessions/{id<\d+>}/abandonner', name: 'training_abandon', methods: ['POST'])]
    public function abandon(Training $training, Request $request): Response
    {
        $this->see($training);
        $this->checkToken('training'.$training->id, $request->request->getString('_token'));
        if ($training->isLive()) {
            $this->trainer->abandon($training);
        }

        return $this->redirectToRoute('training', ['id' => $training->id]);
    }

    private function see(Training $training, bool $managerToo = false): void
    {
        $me = $this->me();
        if ($training->seller !== $me && !($managerToo && $me->isManager() && $training->seller->workspace === $me->workspace)) {
            throw $this->createNotFoundException();
        }
    }
}
