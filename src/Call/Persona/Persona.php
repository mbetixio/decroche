<?php

declare(strict_types=1);

namespace App\Call\Persona;

use App\Ai\LanguageModel;
use App\Entity\Call;
use App\Enum\Speaker;
use App\Repository\CompanyRepository;

/**
 * The seller's counterpart: the model playing the persona, in one pass and as a stream (no tool call, so no second
 * round trip to the model). Its reply comes piece by piece, cleaned of its tags (see {@see ReplyFilter}).
 */
final readonly class Persona
{
    public function __construct(
        private LanguageModel $model,
        private PersonaPrompt $prompt,
        private CompanyRepository $companies,
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
