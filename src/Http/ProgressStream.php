<?php

declare(strict_types=1);

namespace App\Http;

use App\Call\CallException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A long piece of work (a call written, a debrief, an analysis) told step by step, as JSON lines: a "step" line as each
 * step starts (what the agent is really doing: reading the seller's latest calls, checking a quote…), then "done" with
 * the address to go to, or "error" with what to tell the user. The waiting screen ticks the steps off as they come.
 */
final readonly class ProgressStream
{
    public const string TYPE = 'application/x-ndjson';

    public function __construct(
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Whether the client asked to follow the work step by step (the waiting screens do; a form without JavaScript does
     * not, and gets the page as before).
     */
    public static function wanted(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Accept'), self::TYPE);
    }

    /**
     * @param \Closure(\Closure(string, array<string, string>=): void): string $work  does the work, telling its steps, and gives the address to go to
     * @param \Closure(\Throwable): array{error: string, url?: string}         $error what to tell the user when the work fails (and where to go then, if anywhere)
     */
    public function response(Request $request, \Closure $work, \Closure $error): StreamedResponse
    {
        // The session is released: the rest of the site answers while the work goes on.
        if ($request->hasSession()) {
            $request->getSession()->save();
        }
        $translator = $this->translator;
        $logger = $this->logger;
        $locale = $request->getLocale();

        return new StreamedResponse(static function () use ($work, $error, $translator, $logger, $locale): void {
            // The work runs while the answer is sent, once the request is over: its language is set again.
            if ($translator instanceof LocaleAwareInterface) {
                $translator->setLocale($locale);
            }
            OutputBuffers::release();
            $send = static function (array $event): void {
                echo json_encode($event, \JSON_UNESCAPED_UNICODE)."\n";
                flush();
            };
            try {
                $url = $work(static fn (string $key, array $params = []) => $send(['t' => 'step', 'key' => $key, 'label' => $translator->trans('steps.'.$key, $params)]));
                $send(['t' => 'done', 'url' => $url]);
            } catch (\Throwable $e) {
                $failure = $error($e);
                if (!$e instanceof CallException) {
                    $logger->error('A long piece of work failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                }
                // A refusal the user can act on (a spent quota, an abandoned call) is said as is, not as a failure.
                if ($e instanceof CallException) {
                    $failure['error'] = $translator->trans($e->getMessage());
                }
                $send(['t' => 'error'] + $failure);
            }
        }, StreamedResponse::HTTP_OK, ['Content-Type' => self::TYPE.'; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Accel-Buffering' => 'no']);
    }
}
