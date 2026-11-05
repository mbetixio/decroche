<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A persona's state of mind on the phone: how they talk, and how their voice carries it (rate and pitch).
 */
enum Tone: string
{
    case Warm = 'chaleureux';
    case Enthusiastic = 'enthousiaste';
    case Direct = 'direct';
    case Relaxed = 'detendu';
    case Careful = 'prudent';
    case Busy = 'presse';
    case Curious = 'curieux';
    case Wary = 'mefiant';
    case Stressed = 'stresse';
    case Worried = 'inquiet';
    case Angry = 'fache';
    case LetDown = 'decu';

    /** Situations that call for another tone than everyday ones (an unhappy client is not relaxed). */
    private const array BY_SITUATION = [
        'client_mecontent' => [self::Angry, self::Angry, self::LetDown, self::Stressed],
        'depart_dev' => [self::Worried, self::Stressed, self::Angry],
        'resiliation' => [self::LetDown, self::Direct, self::Careful, self::Angry],
        'echaude_offshore' => [self::Wary, self::LetDown, self::Careful],
        'urgence' => [self::Stressed, self::Busy],
        'depart_cto' => [self::Worried, self::Stressed, self::Direct],
        'suivi_projet' => [self::Warm, self::Direct, self::LetDown, self::Relaxed],
        'renouvellement' => [self::Direct, self::Careful, self::Busy],
        'barrage' => [self::Warm, self::Busy, self::Careful, self::Direct],
    ];

    public function label(): string
    {
        return match ($this) {
            self::Warm => 'chaleureux et bavard, il aime raconter et met vite à l\'aise',
            self::Enthusiastic => 'entrepreneur enthousiaste, plein d\'idées, il parle vite de sa vision',
            self::Direct => 'direct et carré, il va droit au but mais reste correct',
            self::Relaxed => 'sympa et détendu, il plaisante volontiers',
            self::Careful => 'posé et prudent, il réfléchit avant de répondre et pose des questions',
            self::Busy => 'pressé mais courtois, il est entre deux réunions',
            self::Curious => 'curieux et technique, il aime comprendre comment ça marche',
            self::Wary => 'méfiant mais ouvert si on l\'écoute vraiment',
            self::Stressed => 'stressé par une échéance, un peu tendu',
            self::Worried => 'inquiet, il cherche à être rassuré',
            self::Angry => 'fâché, il ne décolère pas et le fait entendre, sans être grossier',
            self::LetDown => 'déçu et un peu las, il y avait cru',
        };
    }

    /**
     * The speech rate of their voice, for the speech synthesizer.
     */
    public function rate(): string
    {
        return match ($this) {
            self::Warm => '+8%',
            self::Enthusiastic, self::Stressed => '+18%',
            self::Direct => '+14%',
            self::Relaxed, self::Curious, self::Worried => '+10%',
            self::Careful => '+4%',
            self::Busy, self::Angry => '+20%',
            self::Wary => '+8%',
            self::LetDown => '+2%',
        };
    }

    /**
     * The pitch of their voice, for the speech synthesizer.
     */
    public function pitch(): string
    {
        return match ($this) {
            self::Warm, self::Busy => '+1Hz',
            self::Enthusiastic, self::Angry => '+4Hz',
            self::Direct => '-1Hz',
            self::Relaxed, self::Worried => '+2Hz',
            self::Careful, self::Wary => '-2Hz',
            self::Curious => '+0Hz',
            self::Stressed => '+3Hz',
            self::LetDown => '-4Hz',
        };
    }

    /**
     * The tones a situation may draw: most calls are with ordinary, rather pleasant people; angry or let-down
     * clients only where the situation calls for them.
     *
     * @return non-empty-list<self>
     */
    public static function forSituation(string $key): array
    {
        return self::BY_SITUATION[$key]
            ?? (preg_match('/mecontent|resili|plainte|litige|insatisf|colere|reclam|retard|incident/', $key)
                ? [self::Angry, self::LetDown, self::Stressed, self::Worried]
                : [self::Warm, self::Warm, self::Enthusiastic, self::Direct, self::Direct, self::Relaxed, self::Relaxed, self::Careful, self::Busy, self::Curious, self::Wary]);
    }
}
