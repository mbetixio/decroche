<?php

declare(strict_types=1);

namespace App\Call\Persona;

use App\Enum\BriefSection;

/**
 * Cleans the persona's reply as it streams in. The persona marks what it does with tags the seller must never see or
 * hear: [devoiler:budget] when it gives a brief section away, [fin] when it hangs up. This filter applies and removes
 * them, holds back a half-received tag until it closes, drops the persona's name when the model writes it before its
 * line, and quotation marks around the line.
 */
final class ReplyFilter
{
    private string $buffer = '';
    private string $said = '';
    private bool $started = false;

    /** @var list<BriefSection> */
    public private(set) array $revealed = [];

    public private(set) bool $hungUp = false;

    public function __construct(
        private readonly string $personaName,
    ) {
    }

    /**
     * Takes a piece of the reply and returns what can already be said.
     */
    public function feed(string $chunk): string
    {
        $this->buffer .= $chunk;

        return $this->flush(false);
    }

    /**
     * Returns what is left once the reply is complete.
     */
    public function finish(): string
    {
        return $this->flush(true);
    }

    /**
     * The whole cleaned reply.
     */
    public function text(): string
    {
        // One spoken line: the line breaks between a tool's rounds become a space, like any other run of spaces.
        return trim((string) preg_replace('/\s{2,}|\n/u', ' ', $this->said));
    }

    private function flush(bool $final): string
    {
        // Complete tags are applied, then removed; any other bracketed text is a stage direction, dropped too.
        $this->buffer = (string) preg_replace_callback('/\[([^\]\[]{0,60})\]/u', function (array $m): string {
            if (preg_match('/^\s*d[ée]voiler\s*[:=]\s*([a-z]+)/iu', $m[1], $tag) && null !== $section = BriefSection::tryFrom(strtolower($tag[1]))) {
                $this->revealed[] = $section;
            } elseif (preg_match('/^\s*(fin|mettre_fin|raccroche)/iu', $m[1])) {
                $this->hungUp = true;
            }

            return '';
        }, $this->buffer);

        if (!$this->started) {
            // The model sometimes writes its name before its line: wait until that is ruled out.
            if (!$final && mb_strlen(ltrim($this->buffer)) < mb_strlen($this->personaName) + 4) {
                return '';
            }
            $this->buffer = (string) preg_replace('/^\s*\*{0,2}'.preg_quote($this->personaName, '/').'\*{0,2}\s*:\s*/u', '', ltrim($this->buffer));
            $this->started = true;
        }

        // An opening bracket not closed yet may be the start of a tag: hold it back.
        $open = mb_strrpos($this->buffer, '[');
        $cut = $final || false === $open ? mb_strlen($this->buffer) : $open;
        $out = str_replace(['«', '»'], '', mb_substr($this->buffer, 0, $cut));
        $this->buffer = mb_substr($this->buffer, $cut);
        $this->said .= $out;

        return $out;
    }
}
