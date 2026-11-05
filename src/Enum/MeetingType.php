<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The kind of meeting a scenario trains for.
 */
enum MeetingType: string
{
    case Discovery = 'decouverte';
    case Objection = 'objection';
    case Negotiation = 'negociation';
    case Unhappy = 'mecontent';
    case Rushed = 'presse';
    case Closing = 'closing';

    public function label(): string
    {
        return match ($this) {
            self::Discovery => 'Rendez-vous de découverte',
            self::Objection => 'Traitement d\'objections',
            self::Negotiation => 'Négociation',
            self::Unhappy => 'Client mécontent',
            self::Rushed => 'Prospect pressé',
            self::Closing => 'Closing',
        };
    }

    /**
     * The meeting type of a call situation, guessed from its key and family.
     */
    public static function ofSituation(string $key, SituationFamily $family): self
    {
        return match (true) {
            (bool) preg_match('/mecontent|resili|plainte|litige|arret|depart_dev/', $key) => self::Unhappy,
            (bool) preg_match('/renouvel|concurrent|forfait|remise/', $key) => self::Negotiation,
            (bool) preg_match('/objection|echaude/', $key) => self::Objection,
            (bool) preg_match('/relance/', $key) => self::Closing,
            (bool) preg_match('/prix|urgence|levee/', $key) => self::Rushed,
            SituationFamily::Negotiation === $family => self::Negotiation,
            default => self::Discovery,
        };
    }
}
