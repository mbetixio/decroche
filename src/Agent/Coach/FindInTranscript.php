<?php

declare(strict_types=1);

namespace App\Agent\Coach;

use App\Agent\AgentContext;
use App\Model\Line;
use App\Model\Text;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * The seller's lines of the call holding some words, as said: the coach quotes them word for word.
 */
#[AsTool('find_in_transcript', 'The seller\'s lines of this call that hold these words, exactly as said, to quote them word for word.')]
final readonly class FindInTranscript
{
    public function __construct(
        private AgentContext $context,
    ) {
    }

    /**
     * @param string $words a few words of the line
     *
     * @return list<string>
     */
    public function __invoke(string $words): array
    {
        $needles = Text::words($words);
        $lines = array_filter($this->context->call()->lines(), static fn (Line $l): bool => $l->isSeller());

        return array_values(array_map(static fn (Line $l): string => $l->text, array_filter($lines, static function (Line $l) use ($needles): bool {
            $text = Text::normalize($l->text);

            return [] === array_filter($needles, static fn (string $w): bool => !str_contains($text, $w));
        })));
    }
}
