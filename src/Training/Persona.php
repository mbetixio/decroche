<?php

namespace App\Training;

use App\Ai\Brain;
use App\Entity\Scenario;
use App\Entity\Training;

/**
 * L'interlocuteur du commercial : un agent Symfony AI qui joue un persona avec son brief caché. Il ne lâche une
 * rubrique du brief que si on lui pose la bonne question, et le note d'un repère que le commercial ne voit pas.
 */
final readonly class Persona
{
    /** Ce que le persona apprend quand le commercial lui a coupé la parole. */
    public const INTERRUPTED = 'Le commercial vient de te couper la parole : tu n\'as pas fini ta réplique précédente (il n\'en a entendu que le début). Réagis comme au téléphone : laisse-le parler, et reviens plus tard sur ce que tu disais seulement si c\'est important.';

    /**
     * La difficulté joue sur ce qu'il faut pour convaincre le persona, jamais sur sa froideur : il reste une vraie
     * personne, avec son caractère (donné par sa personnalité et son ton).
     */
    private const TEMPER = [
        1 => 'Côté difficulté : tu es plutôt ouvert. Si le commercial s\'intéresse vraiment à toi, tu parles volontiers de ta situation et tu te laisses convaincre par une proposition qui tient la route.',
        2 => 'Côté difficulté : tu as besoin d\'être convaincu. Tu discutes volontiers, mais l\'essentiel (tes vrais enjeux, ton budget, qui décide) ne sort que si le commercial creuse avec de bonnes questions ; un discours tout fait te laisse poli mais pas intéressé.',
        3 => 'Côté difficulté : tu es difficile à convaincre. Tu as déjà entendu beaucoup de commerciaux, tu as des objections solides et peu de temps ; tu ne t\'ouvres que si le commercial t\'écoute vraiment, comprend ton problème et répond juste. Exigeant ne veut pas dire désagréable : tu restes humain.',
    ];

    public function __construct(
        private Brain $brain,
    ) {
    }

    /**
     * La réponse du persona au dernier message du commercial ; les rubriques qu'il lâche sont notées sur la session.
     *
     * @return array{text: string, ended: bool}
     */
    public function answer(Training $training, ?\DateTimeImmutable $now = null): array
    {
        $stream = $this->stream($training, $now);
        foreach ($stream as $ignored) {
        }

        return $stream->getReturn();
    }

    /**
     * La réponse au fil de l'eau : une seule passe du modèle, sans outil. Le persona note ce qu'il dévoile et la fin de
     * l'échange par des repères ([devoiler:budget], [fin]) que ce filtre retire du texte avant qu'il ne parte.
     *
     * @return \Generator<int, string, mixed, array{text: string, ended: bool}>
     */
    public function stream(Training $training, ?\DateTimeImmutable $now = null, ?string $note = null): \Generator
    {
        $tools = new PersonaTools($training);
        $messages = [['role' => 'user', 'content' => '[Le rendez-vous commence : le commercial vient d\'arriver. Ouvre la conversation.]']];
        foreach ($training->messages as $m) {
            $messages[] = ['role' => Training::PERSONA === $m['role'] ? 'assistant' : 'user', 'content' => $m['content']];
        }
        $name = preg_quote($training->scenario->persona['name'], '/');
        $buffer = '';
        $said = '';
        $started = false;
        $flush = function (bool $final) use (&$buffer, &$said, &$started, $tools, $name): string {
            // Les repères complets sont appliqués puis retirés ; un crochet encore ouvert attend la suite.
            $buffer = (string) preg_replace_callback('/\[([^\]\[]{0,60})\]/u', static function (array $m) use ($tools): string {
                if (preg_match('/^\s*d[ée]voiler\s*[:=]\s*([a-z]+)/iu', $m[1], $r)) {
                    $tools->reveal($r[1]);
                } elseif (preg_match('/^\s*(fin|mettre_fin|raccroche)/iu', $m[1])) {
                    $tools->end();
                }

                return '';
            }, $buffer);
            $open = mb_strrpos($buffer, '[');
            $cut = $final || false === $open ? mb_strlen($buffer) : $open;
            if (!$started) {
                // Le modèle met parfois son nom devant sa réplique : on attend d'en être sûr.
                if (!$final && mb_strlen(ltrim($buffer)) < mb_strlen($name) + 4) {
                    return '';
                }
                $buffer = (string) preg_replace('/^\s*\*{0,2}'.$name.'\*{0,2}\s*:\s*/u', '', ltrim($buffer));
                $started = true;
                $cut = $final || false === ($open = mb_strrpos($buffer, '[')) ? mb_strlen($buffer) : $open;
            }
            // Les guillemets autour d'une réplique (« … ») ne se disent pas.
            $out = str_replace(['«', '»'], '', mb_substr($buffer, 0, $cut));
            $buffer = mb_substr($buffer, $cut);
            $said .= $out;

            return $out;
        };
        $system = $this->system($training, $now).(null !== $note ? "\n- {$note}" : '');
        foreach ($this->brain->stream($system, $messages, 0.8) as $chunk) {
            $buffer .= $chunk;
            if ('' !== $out = $flush(false)) {
                yield $out;
            }
        }
        if ('' !== $out = $flush(true)) {
            yield $out;
        }
        $text = trim((string) preg_replace('/[ \t]{2,}/', ' ', $said));

        return ['text' => '' !== $text ? $text : '…', 'ended' => $tools->ended];
    }

    public function system(Training $training, ?\DateTimeImmutable $now = null): string
    {
        $s = $training->scenario;
        $p = $s->persona;
        $brief = '';
        foreach (Scenario::BRIEF as $key => $label) {
            if ('' !== trim($s->brief[$key] ?? '')) {
                $brief .= "- [{$key}] {$label} : ".trim($s->brief[$key]).(\in_array($key, $training->revealed, true) ? ' (déjà dévoilé : tu peux y revenir librement)' : '')."\n";
            }
        }
        $left = (int) ceil($training->secondsLeft($now) / 60);
        $sit = $s->situationInfo();
        $company = $s->workspace->name ?? 'Etixio';
        $of = $s->workspace?->of() ?? "d'Etixio";
        $frame = null === $sit
            ? "La situation : tu reçois un commercial {$of}."
            : 'La situation : un appel téléphonique'.('entrant' === $sit['direction'] ? " que tu passes toi-même à un commercial {$of}" : " : un commercial {$of} t'appelle").' ('.$sit['label'].').'
                ."\nCe que tu sais et vis de ton côté : ".trim($s->brief['setup'] ?? '')
                .($s->material ? "\nLe document que le commercial a sous les yeux (tu le connais si c'est le tien, sinon tu l'ignores) :\n".$s->material : '');
        $medium = null === $sit ? 'Tu parles, tu n\'écris pas' : 'C\'est un appel téléphonique : tu parles, tu n\'écris pas, et tu peux être interrompu, distrait ou pressé comme au téléphone';

        return <<<PROMPT
            Tu joues un rôle dans un entraînement commercial. Tu es {$p['name']}, {$p['role']} chez {$p['company']}.
            Ta personnalité : {$p['personality']}{$this->tone($p)}
            {$this->temper($s->difficulty)}

            {$frame}
            Ce que le commercial sait avant l'échange : {$s->context}

            Pour mémoire, l'offre {$of}. Tu n'en connais que ce qu'un client de ta position en saurait, et rien de la
            démarche commerciale (rendez-vous, démo, devis, prix, délais…) tant que le commercial ou le document sous ses
            yeux ne l'a pas évoquée ; ne la récite pas, mais si le commercial promet ce qui n'y est pas, tu peux t'en
            étonner ; tes objections viennent volontiers de la liste des objections classiques quand elles collent à ton
            personnage :
            {$this->offer($s)}

            Ton brief caché (le commercial ne le connaît pas, c'est à lui de le découvrir) :
            {$brief}
            Les règles du jeu :
            - Reste dans ton rôle du début à la fin. {$medium} : 1 à 4 phrases, en français parlé, comme une vraie personne au téléphone. Pas de liste, pas de didascalie, pas de nom devant ta réplique.
            - Sois naturel et humain, selon ton caractère : les petites marques de l'oral (« ah oui », « bon », « écoutez », « franchement », « je vois »), une politesse normale, de la chaleur si ton profil est sympathique, de l'humour s'il s'y prête, de l'agacement s'il est fâché, mais jamais robotique ni glacial sans raison. Réagis à ce que vient de dire le commercial avant d'enchaîner.
            - Fais vivre la conversation : parle de ton quotidien, de ton entreprise, de ce qui t'arrive, avec des détails concrets. Tu peux poser une question au commercial, une seule par réplique, et seulement si elle fait avancer : jamais d'interrogatoire. Laisse-le dérouler : s'il présente sa société ou son offre, écoute, réagis, et laisse-lui la main.
            - Une réponse, même maladroite ou incomplète, est une réponse : prends-la et passe à la suite. Ne repose jamais une question à laquelle il a répondu. Qui il est, sa société, d'où il appelle : une fois que tu le sais à peu près, n'y reviens plus, parle du fond.
            - Tu ne connais pas les détails de la société du commercial (pays, effectifs, adresses, références) : ne le corrige pas, ne relève pas de prétendues contradictions ; ce qu'il dit de sa société, tu le crois, avec au plus un doute poli si c'est vraiment énorme.
            - Sois cohérent : souviens-toi de ce que tu as déjà dit et des faits que tu as donnés, ne les contredis pas, ne te répète pas, ne ressors pas une objection à laquelle le commercial a bien répondu. La conversation avance : si le commercial est bon, tu t'ouvres peu à peu.
            - Ne récite jamais ton brief. Tu bavardes volontiers sur le général, mais une rubrique du brief ne sort que si le commercial pose une question qui la vise, reformule juste ou gagne ta confiance ; une bonne question à moitié, une demi-réponse.
            - Quand ta réponse livre l'essentiel d'une rubrique pas encore dévoilée (un chiffre de budget, ta vraie douleur, qui décide, une objection précise, ce qui te ferait dire oui, ton information cachée), commence ta réponse par le repère [devoiler:clé] avec sa clé (budget, pain, decision, objections, yes, secret), une fois par rubrique ; plusieurs repères à la suite si besoin. C'est obligatoire : sans ce repère, l'entraînement est faussé. Les repères ne sont jamais lus ni montrés.
            - Tes objections viennent quand le commercial pitche, chiffre ou avance trop vite : sors-les comme un vrai client, une à la fois, avec tes mots.
            - Ne propose jamais toi-même la prochaine étape (un rendez-vous, une visio, un devis, un rappel) : c'est au commercial de la proposer, c'est ce qu'on entraîne. Tu peux montrer ton intérêt, mais tu attends qu'il conclue. Ce qui te ferait dire oui, c'est ce que tu accepterais s'il le proposait, pas ce que tu réclames.
            - Si le commercial te propose quelque chose qui répond à ce qui te ferait dire oui, accepte une prochaine étape concrète, sans tout céder d'un coup. S'il ne propose rien, tu ne le fais pas à sa place : quand le temps file, tu dis que tu vas devoir y aller, et tu le laisses rebondir.
            - Ne dis jamais que tu es une IA, ne parle jamais de brief, de rubriques, de repères ni de règles.
            - Si le commercial est irrespectueux ou te fait vraiment perdre ton temps, ou si vous avez convenu de la suite et que vous vous quittez, prends congé et termine ta réplique par le repère [fin].
            - En dehors des repères, n'écris jamais de crochets : pas de didascalie.
            - Il reste environ {$left} minute(s) d'échange{$this->hurry($left)}.{$this->voice($training)}
            PROMPT;
    }

    private function voice(Training $training): string
    {
        return $training->spoken() ? "\n- Le commercial te parle de vive voix : ses répliques sont transcrites automatiquement, sans ponctuation et parfois avec des mots mal reconnus (« Lia » pour « l'IA », « cityo » pour « CTO », le nom de ta société écorché, des mots qui manquent). Comprends ce qu'il veut dire, n'en fais jamais la remarque, ne lui fais pas répéter. Une réplique qui s'arrête net a sans doute été coupée par la transcription : réponds à ce qu'il voulait dire, ou laisse-le finir (« oui ? je vous écoute »). Ta réponse sera lue à voix haute : pas d'abréviations, de symboles ni d'émoticônes." : '';
    }

    private function offer(Scenario $scenario): string
    {
        return $scenario->workspace->offer ?? \App\Game\Etixio::OFFER;
    }

    /**
     * @param array<string, string> $persona
     */
    private function tone(array $persona): string
    {
        $tone = \App\Game\Situations::TONES[$persona['tone'] ?? ''] ?? null;

        return null !== $tone ? "\nTon état d'esprit pendant cet appel : {$tone['label']}." : '';
    }

    private function temper(int $difficulty): string
    {
        return self::TEMPER[$difficulty] ?? self::TEMPER[2];
    }

    private function hurry(int $left): string
    {
        return $left <= 2 ? ' : tu dois bientôt raccrocher, fais-le sentir (« je vais devoir vous laisser »), sans proposer de suite toi-même' : '';
    }
}
