<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\Language;

/**
 * One line of the company's scorecard: what the coach grades on every call, and what it expects.
 */
final readonly class Criterion
{
    public function __construct(
        public string $key,
        public string $label,
        public string $hint = '',
    ) {
    }

    /**
     * Décroche's own scorecard, in the working language, used until the company writes its own.
     *
     * @return array<string, self>
     */
    public static function defaults(Language $language = Language::French): array
    {
        $list = match ($language) {
            Language::French => [
                new self('discovery', 'Découverte du besoin', 'fait parler le client de sa situation, de ses enjeux, de son contexte avant de proposer'),
                new self('questions', 'Questions ouvertes', 'des questions ouvertes, précises, une à la fois, qui font avancer'),
                new self('listening', 'Écoute et reformulation', 'rebondit sur ce que dit le client, reformule, ne coupe pas, laisse parler'),
                new self('objections', 'Traitement des objections', 'accueille l\'objection, la creuse, y répond avec les vrais arguments de l\'offre'),
                new self('value', 'Proposition de valeur', 'relie l\'offre au besoin exprimé, sans catalogue ni promesse hors offre'),
                new self('closing', 'Closing / prochaine étape', 'propose lui-même une prochaine étape concrète, datée, et la verrouille'),
            ],
            Language::English => [
                new self('discovery', 'Needs discovery', 'gets the client talking about their situation, stakes and context before proposing'),
                new self('questions', 'Open questions', 'open, precise questions, one at a time, that move things forward'),
                new self('listening', 'Listening and rephrasing', 'builds on what the client says, rephrases, does not interrupt, lets them talk'),
                new self('objections', 'Handling objections', 'welcomes the objection, digs into it, answers with the offer\'s real arguments'),
                new self('value', 'Value proposition', 'ties the offer to the stated need, no catalogue, no promise outside the offer'),
                new self('closing', 'Closing / next step', 'proposes a concrete, dated next step themself and locks it in'),
            ],
        };

        return array_combine(array_map(static fn (self $c): string => $c->key, $list), $list);
    }

    /**
     * @return array{label: string, hint: string}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'hint' => $this->hint];
    }
}
