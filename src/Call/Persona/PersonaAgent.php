<?php

declare(strict_types=1);

namespace App\Call\Persona;

use App\Agent\AgentContext;
use App\Ai\LanguageModel;
use App\Entity\Call;
use App\Enum\Speaker;
use App\Repository\CompanyRepository;

/**
 * The seller's counterpart: the persona agent, answering as a stream. It may look something up first with its tools
 * (its files, its calendar, its earlier calls with the seller), then its reply comes piece by piece, cleaned of its tags
 * (see {@see ReplyFilter}).
 */
final readonly class PersonaAgent
{
    public function __construct(
        private LanguageModel $model,
        private PersonaPrompt $prompt,
        private CompanyRepository $companies,
        private AgentContext $context,
    ) {
    }

    /**
     * The persona's reply to the conversation so far, as it is written.
     *
     * @param string|null $note something the persona should know for this reply (it was cut off…)
     *
     * @return \Generator<int, string, mixed, Reply> the pieces of the reply; the complete reply when done
     */
    public function reply(Call $call, \DateTimeImmutable $now, ?string $note = null): \Generator
    {
        $messages = [['role' => 'user', 'content' => '[The meeting starts: the seller has just arrived. Open the conversation.]']];
        foreach ($call->lines() as $line) {
            $messages[] = ['role' => Speaker::Persona === $line->speaker ? 'assistant' : 'user', 'content' => $line->text];
        }
        // Their tools (files, calendar, memory) work on this call.
        $this->context->forCall($call);
        $filter = new ReplyFilter($call->scenario->persona()->name);
        $system = $this->prompt->build($call, $this->companies->get(), $now, $note);
        foreach ($this->model->stream($system, $messages, 0.8) as $chunk) {
            if ('' !== $out = $filter->feed($chunk)) {
                yield $out;
            }
        }
        if ('' !== $out = $filter->finish()) {
            yield $out;
        }

        return new Reply('' !== $filter->text() ? $filter->text() : '…', $filter->revealed, $filter->hungUp);
    }
}
