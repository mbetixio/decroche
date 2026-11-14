<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\Data;
use App\Ai\ModelException;
use App\Call\CallException;
use App\Call\CallManager;
use App\Call\Coach\Debrief;
use App\Call\TurnStreamer;
use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\Speaker;
use App\Http\OutputBuffers;
use App\Http\ProgressStream;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use App\Security\Voter\CallVoter;
use App\Security\Voter\ScenarioVoter;
use App\Voice\SpeechSynthesizer;
use App\Voice\SynthesisException;
use App\Voice\VoiceCasting;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A call on the phone screen: pick up, talk (the persona answers as a stream, voiced sentence by sentence), hang up,
 * then the debrief and the transcript, from which a moment can be replayed.
 */
#[Route('/calls')]
final class CallController extends AbstractController
{
    public function __construct(
        private readonly CallManager $calls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Picks up: the call starts on the scenario (a call already in progress is abandoned).
     */
    #[Route('/start/{id<\d+>}', name: 'call_start', methods: ['POST'])]
    #[IsGranted(ScenarioVoter::VIEW, 'scenario')]
    #[IsCsrfTokenValid(new Expression('"call-start-" ~ args["scenario"].id'))]
    public function start(Scenario $scenario, #[CurrentUser] Member $me): Response
    {
        try {
            $call = $this->calls->start($me, $scenario);
        } catch (CallException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('call_briefing', ['id' => $scenario->id]);
        }

        return $this->redirectToRoute('call_show', ['id' => $call->id, 'pickup' => 1]);
    }

    /**
     * The call: the phone screen while it is live, then its debrief and transcript.
     */
    #[Route('/{id<\d+>}', name: 'call_show', methods: ['GET'])]
    #[IsGranted(CallVoter::VIEW, 'call')]
    public function show(Call $call, #[CurrentUser] Member $me, SpeechSynthesizer $synthesizer, VoiceCasting $casting, CompanyRepository $companies, ClockInterface $clock): Response
    {
        $this->calls->expire($call);
        $company = $companies->get();
        if ($call->isLive() && $call->member === $me) {
            return $this->render('call/phone.html.twig', [
                'call' => $call,
                'scenario' => $call->scenario,
                'voice' => $synthesizer->isAvailable() ? $casting->voice($call->scenario, $company->language) : null,
                'secondsLeft' => $call->secondsLeft($clock->now()),
            ]);
        }

        return $this->render('call/show.html.twig', [
            'call' => $call,
            'debrief' => null !== $call->debrief ? Debrief::fromArray($call->debrief) : null,
            'mine' => $call->member === $me,
            'now' => $clock->now(),
        ]);
    }

    /**
     * One exchange, streamed as JSON lines (see {@see TurnStreamer}).
     */
    #[Route('/{id<\d+>}/talk', name: 'call_talk', methods: ['POST'], format: 'json')]
    #[IsGranted(CallVoter::PLAY, 'call')]
    #[IsCsrfTokenValid(new Expression('"call-" ~ args["call"].id'), tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
    public function talk(Call $call, Request $request, TurnStreamer $streamer, LoggerInterface $logger): Response
    {
        set_time_limit(180);
        $body = $request->toArray();
        $voiceMs = is_numeric($body['voiceMs'] ?? null) ? (int) $body['voiceMs'] : null;
        try {
            $events = $streamer->stream($call, \is_string($body['message'] ?? null) ? $body['message'] : '', $voiceMs, true === ($body['interrupted'] ?? false), '1' !== $request->query->get('browserVoice'));
        } catch (CallException $e) {
            return new JsonResponse(['error' => $this->translator->trans($e->getMessage()), 'ended' => !$call->isLive()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            // The model is unreachable or failed: the seller says it again.
            $logger->warning('The persona could not answer: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(['error' => $this->translator->trans('call.error.unreachable')], Response::HTTP_BAD_GATEWAY);
        }
        // The session is released: the rest of the site answers while the persona speaks.
        if ($request->hasSession()) {
            $request->getSession()->save();
        }
        $translator = $this->translator;

        return new StreamedResponse(static function () use ($events, $translator): void {
            // Each sentence must leave at once: no output buffer may hold it back.
            OutputBuffers::release();
            foreach ($events as $event) {
                if ('error' === $event['t']) {
                    $event['error'] = $translator->trans(Data::string($event['error']));
                }
                echo json_encode($event, \JSON_UNESCAPED_UNICODE)."\n";
                flush();
            }
        }, Response::HTTP_OK, ['Content-Type' => 'application/x-ndjson; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }

    /**
     * The MP3 of one of the persona's lines, in their voice (always the same for them).
     */
    #[Route('/{id<\d+>}/voice/{n<\d+>}', name: 'call_voice', methods: ['GET'])]
    #[IsGranted(CallVoter::VIEW, 'call')]
    public function voice(Call $call, int $n, SpeechSynthesizer $synthesizer, VoiceCasting $casting, CompanyRepository $companies): Response
    {
        $line = $call->lines()[$n] ?? null;
        if (null === $line || Speaker::Persona !== $line->speaker) {
            throw $this->createNotFoundException();
        }
        set_time_limit(60);
        try {
            $audio = $synthesizer->speak($line->text, $casting->voice($call->scenario, $companies->get()->language), $casting->prosody($call->scenario));
        } catch (SynthesisException) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response($audio, Response::HTTP_OK, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Hangs up and has the coach write the debrief (some thirty seconds).
     */
    #[Route('/{id<\d+>}/finish', name: 'call_finish', methods: ['POST'])]
    #[IsGranted(CallVoter::PLAY, 'call')]
    #[IsCsrfTokenValid(new Expression('"call-" ~ args["call"].id'))]
    public function finish(Call $call, Request $request, ProgressStream $progress): Response
    {
        set_time_limit(300);
        $show = $this->generateUrl('call_show', ['id' => $call->id]);
        if (ProgressStream::wanted($request)) {
            return $progress->response(
                $request,
                function (\Closure $step) use ($call, $show): string {
                    $step('call.hung_up');
                    $this->calls->finish($call, $step);

                    return $show;
                },
                // The call page offers to write the debrief again.
                fn (): array => ['error' => $this->translator->trans('call.error.debrief_failed'), 'url' => $show],
            );
        }
        try {
            $this->calls->finish($call);
        } catch (CallException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (ModelException|\RuntimeException) {
            $this->addFlash('error', 'call.error.debrief_failed');
        }

        return $this->redirectToRoute('call_show', ['id' => $call->id]);
    }

    #[Route('/{id<\d+>}/abandon', name: 'call_abandon', methods: ['POST'])]
    #[IsGranted(CallVoter::PLAY, 'call')]
    #[IsCsrfTokenValid(new Expression('"call-" ~ args["call"].id'))]
    public function abandon(Call $call): Response
    {
        if ($call->isLive()) {
            $this->calls->abandon($call);
        }

        return $this->redirectToRoute('call_show', ['id' => $call->id]);
    }

    /**
     * Replays a moment: the call resumes just before one of the seller's lines, to try it another way.
     */
    #[Route('/{id<\d+>}/replay/{n<\d+>}', name: 'call_replay', methods: ['POST'])]
    #[IsGranted(CallVoter::PLAY, 'call')]
    #[IsCsrfTokenValid(new Expression('"replay-" ~ args["call"].id'))]
    public function replay(Call $call, int $n): Response
    {
        try {
            $replay = $this->calls->replay($call, $n);
        } catch (CallException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('call_show', ['id' => $call->id]);
        }

        return $this->redirectToRoute('call_show', ['id' => $replay->id, 'pickup' => 1]);
    }

    /**
     * The member's calls.
     */
    #[Route('', name: 'call_history', methods: ['GET'])]
    public function history(#[CurrentUser] Member $me, CallRepository $repository): Response
    {
        return $this->render('call/history.html.twig', ['calls' => $repository->history($me, 100)]);
    }
}
