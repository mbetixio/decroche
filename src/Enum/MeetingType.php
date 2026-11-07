<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The kind of meeting a scenario trains for.
 */
enum MeetingType: string implements TranslatableInterface
{
    case Discovery = 'discovery';
    case Objection = 'objection';
    case Negotiation = 'negotiation';
    case Unhappy = 'unhappy_client';
    case Rushed = 'rushed';
    case Closing = 'closing';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.meeting_type.'.$this->value, locale: $locale);
    }

    /**
     * Its name in English, for the model's instructions.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Discovery => 'a discovery meeting',
            self::Objection => 'handling objections',
            self::Negotiation => 'a negotiation',
            self::Unhappy => 'an unhappy client',
            self::Rushed => 'a prospect in a hurry',
            self::Closing => 'closing',
        };
    }

    /**
     * The meeting type of a call situation, guessed from its key (French or English) and family.
     */
    public static function ofSituation(string $key, SituationFamily $family): self
    {
        return match (true) {
            (bool) preg_match('/mecontent|unhappy|angry|resili|cancel|churn|plainte|complaint|litige|arret|depart_dev|leaving/', $key) => self::Unhappy,
            (bool) preg_match('/renouvel|renewal|concurrent|competitor|forfait|fixed_price|remise|discount/', $key) => self::Negotiation,
            (bool) preg_match('/objection|echaude|burnt/', $key) => self::Objection,
            (bool) preg_match('/relance|follow_up_quote|quote_follow/', $key) => self::Closing,
            (bool) preg_match('/prix|price|urgence|urgent|by_monday|levee|funding/', $key) => self::Rushed,
            SituationFamily::Negotiation === $family => self::Negotiation,
            default => self::Discovery,
        };
    }
}
