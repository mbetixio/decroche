<?php

declare(strict_types=1);

namespace App\Model;

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
     * Sparring's own scorecard, used until the company writes its own.
     *
     * @return array<string, self>
     */
    public static function defaults(): array
    {
        $list = [
            new self('decouverte', 'Découverte du besoin', 'fait parler le client de sa situation, de ses enjeux, de son contexte avant de proposer'),
            new self('questions', 'Questions ouvertes', 'des questions ouvertes, précises, une à la fois, qui font avancer'),
            new self('ecoute', 'Écoute et reformulation', 'rebondit sur ce que dit le client, reformule, ne coupe pas, laisse parler'),
            new self('objections', 'Traitement des objections', 'accueille l\'objection, la creuse, y répond avec les vrais arguments de l\'offre'),
            new self('valeur', 'Proposition de valeur', 'relie l\'offre au besoin exprimé, sans catalogue ni promesse hors offre'),
            new self('closing', 'Closing / prochaine étape', 'propose lui-même une prochaine étape concrète, datée, et la verrouille'),
        ];

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
