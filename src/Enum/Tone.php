<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A persona's state of mind on the phone: how they talk, and how their voice carries it (rate and pitch).
 */
enum Tone: string
{
    case Warm = 'warm';
    case Enthusiastic = 'enthusiastic';
    case Direct = 'direct';
    case Relaxed = 'relaxed';
    case Careful = 'careful';
    case Busy = 'busy';
    case Curious = 'curious';
    case Wary = 'wary';
    case Stressed = 'stressed';
    case Worried = 'worried';
    case Angry = 'angry';
    case LetDown = 'let_down';

    /** Situations that call for another tone than everyday ones (an unhappy client is not relaxed), by key. */
    private const array BY_SITUATION = [
        'client_mecontent' => [self::Angry, self::Angry, self::LetDown, self::Stressed],
        'unhappy_client' => [self::Angry, self::Angry, self::LetDown, self::Stressed],
        'depart_dev' => [self::Worried, self::Stressed, self::Angry],
        'key_developer_leaving' => [self::Worried, self::Stressed, self::Angry],
        'resiliation' => [self::LetDown, self::Direct, self::Careful, self::Angry],
        'client_wants_to_stop' => [self::LetDown, self::Direct, self::Careful, self::Angry],
        'echaude_offshore' => [self::Wary, self::LetDown, self::Careful],
        'burnt_by_offshore' => [self::Wary, self::LetDown, self::Careful],
        'urgence' => [self::Stressed, self::Busy],
        'developers_by_monday' => [self::Stressed, self::Busy],
        'depart_cto' => [self::Worried, self::Stressed, self::Direct],
        'cto_leaving' => [self::Worried, self::Stressed, self::Direct],
        'suivi_projet' => [self::Warm, self::Direct, self::LetDown, self::Relaxed],
        'project_check_in' => [self::Warm, self::Direct, self::LetDown, self::Relaxed],
        'renouvellement' => [self::Direct, self::Careful, self::Busy],
        'renewal_discount' => [self::Direct, self::Careful, self::Busy],
        'barrage' => [self::Warm, self::Busy, self::Careful, self::Direct],
        'gatekeeper' => [self::Warm, self::Busy, self::Careful, self::Direct],
    ];

    /**
     * Their state of mind, in English, for the model's instructions.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Warm => 'warm and chatty, they like telling stories and quickly put people at ease',
            self::Enthusiastic => 'an enthusiastic founder, full of ideas, talking fast about their vision',
            self::Direct => 'direct and straightforward, they get to the point but stay polite',
            self::Relaxed => 'friendly and relaxed, they joke easily',
            self::Careful => 'calm and careful, they think before answering and ask questions',
            self::Busy => 'busy but courteous, between two meetings',
            self::Curious => 'curious and technical, they like to understand how things work',
            self::Wary => 'wary, but open if really listened to',
            self::Stressed => 'stressed by a deadline, a little tense',
            self::Worried => 'worried, looking to be reassured',
            self::Angry => 'angry, still fuming and letting it show, without being rude',
            self::LetDown => 'disappointed and a little weary, they had believed in it',
        };
    }

    /**
     * The speech rate of their voice, for the speech synthesizer.
     */
    public function rate(): string
    {
        return match ($this) {
            self::Warm, self::Wary => '+8%',
            self::Enthusiastic, self::Stressed => '+18%',
            self::Direct => '+14%',
            self::Relaxed, self::Curious, self::Worried => '+10%',
            self::Careful => '+4%',
            self::Busy, self::Angry => '+20%',
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
            ?? (preg_match('/mecontent|unhappy|angry|resili|cancel|churn|plainte|complaint|insatisf|colere|reclam|retard|late|delay|incident|outage/', $key)
                ? [self::Angry, self::LetDown, self::Stressed, self::Worried]
                : [self::Warm, self::Warm, self::Enthusiastic, self::Direct, self::Direct, self::Relaxed, self::Relaxed, self::Careful, self::Busy, self::Curious, self::Wary]);
    }
}
