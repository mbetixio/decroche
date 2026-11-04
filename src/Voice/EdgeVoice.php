<?php

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Les voix neuronales de Microsoft Edge (Denise, Henri…), générées côté serveur : le navigateur du commercial
 * n'a plus besoin d'avoir de bonnes voix installées, il reçoit un MP3, sous Chrome comme ailleurs.
 *
 * La génération passe par edge-tts (Python), la librairie de référence pour le service de lecture à voix haute d'Edge :
 * Microsoft en change régulièrement le jeton d'accès, edge-tts suit (pip install -U edge-tts). Ce service n'est pas une
 * API publique documentée : s'il ne répond plus, l'appel retombe sur la voix du navigateur.
 */
final readonly class EdgeVoice
{
    /** Le débit : un peu plus vif que la lecture par défaut, comme on parle au téléphone. */
    public const RATE = '+12%';


    /**
     * Les voix françaises, par accent et par genre : Denise et Henri. Les voix « Multilingual » (Vivienne, Rémy), plus
     * vivantes, devinent la langue du texte et lisent en anglais les répliques courtes (« Mmh. ») ou pleines de noms
     * propres, sans moyen de les forcer en français : elles sont écartées.
     */
    public const VOICES = [
        'fr-FR' => [
            'f' => ['fr-FR-DeniseNeural'],
            'm' => ['fr-FR-HenriNeural'],
        ],
        'fr-CA' => [
            'f' => ['fr-CA-SylvieNeural'],
            'm' => ['fr-CA-AntoineNeural', 'fr-CA-JeanNeural', 'fr-CA-ThierryNeural'],
        ],
    ];

    private const MALE_E = '/^(pierre|philippe|st[ée]phane|j[ée]r[ôo]me|antoine|maxime|guillaume|alexandre|christophe|[ée]tienne|th[ée]ophile|r[ée]mi|jules|georges|baptiste|hugues|serge|yves|andr[ée]|claude|dominique|sacha|luca|jean-.+)$/u';

    public function __construct(
        #[Autowire('%env(default:voices_dir:VOICES_DIR)%')]
        private string $cache,
        #[Autowire('%env(default:edge_tts_default:EDGE_TTS)%')]
        private string $binary,
    ) {
    }

    public function available(): bool
    {
        return is_file($this->binary) && is_executable($this->binary);
    }

    /**
     * La voix d'un persona : son genre (donné, ou deviné à son prénom), l'accent de la situation, et son nom pour
     * départager, afin qu'il garde toujours la même voix.
     *
     * @param array{name: string, gender?: string} $persona
     */
    public static function voiceFor(array $persona, string $accent = 'fr-FR'): string
    {
        $first = mb_strtolower(explode(' ', trim($persona['name']))[0]);
        $gender = \in_array($persona['gender'] ?? '', ['f', 'm'], true)
            ? (string) $persona['gender']
            : (preg_match('/(e|a|ie|ine|elle)$/u', $first) && !preg_match(self::MALE_E, $first) ? 'f' : 'm');
        $list = (self::VOICES[$accent] ?? self::VOICES['fr-FR'])['f' === $gender ? 'f' : 'm'];

        return $list[crc32($persona['name']) % \count($list)];
    }

    /**
     * Le MP3 d'une réplique, mis en cache : une réplique déjà dite ne se régénère pas.
     */
    public function speak(string $text, string $voice, string $rate = self::RATE, string $pitch = '+0Hz'): string
    {
        $job = $this->begin($text, $voice, $rate, $pitch);
        $job['process']?->wait();

        return $this->result($job) ?? throw new \RuntimeException('La voix n\'a pas pu être générée'.(null !== $job['process'] ? ' : '.trim($job['process']->getErrorOutput()) : '.'));
    }

    /**
     * Lance la génération sans l'attendre (le flux d'une réponse en lance une par phrase, pendant que le modèle écrit).
     *
     * @return array{file: string, part: string, process: ?Process}
     */
    public function begin(string $text, string $voice, string $rate = self::RATE, string $pitch = '+0Hz'): array
    {
        if (!preg_match('/^[+-]\d{1,2}%$/', $rate) || !preg_match('/^[+-]\d{1,2}Hz$/', $pitch)) {
            throw new \InvalidArgumentException('Débit ou hauteur invalide.');
        }
        $text = trim($text);
        if ('' === $text) {
            throw new \InvalidArgumentException('Rien à dire.');
        }
        if (!preg_match('/^[a-z]{2}-[A-Z]{2}-[A-Za-z]+Neural$/', $voice)) {
            throw new \InvalidArgumentException('Voix inconnue.');
        }
        if (!$this->available()) {
            throw new \RuntimeException('edge-tts n\'est pas installé (voir le README).');
        }
        $file = $this->cache.'/'.sha1($voice.$rate.('+0Hz' !== $pitch ? $pitch : '')."\n".$text).'.mp3';
        if (is_file($file) && filesize($file) > 0) {
            return ['file' => $file, 'part' => '', 'process' => null];
        }
        if (!is_dir($this->cache)) {
            @mkdir($this->cache, 0775, true);
        }
        $part = $file.'.'.bin2hex(random_bytes(3)).'.part';
        $process = new Process([$this->binary, '--voice', $voice, '--rate='.$rate, '--pitch='.$pitch, '--text', $text, '--write-media', $part]);
        $process->setTimeout(30);
        $process->start();

        return ['file' => $file, 'part' => $part, 'process' => $process];
    }

    /**
     * @param array{file: string, part: string, process: ?Process} $job
     */
    public static function done(array $job): bool
    {
        return null === $job['process'] || !$job['process']->isRunning();
    }

    /**
     * Le MP3 d'une génération terminée, ou null si elle a échoué.
     *
     * @param array{file: string, part: string, process: ?Process} $job
     */
    public function result(array $job): ?string
    {
        if (null !== $job['process']) {
            $part = $job['part'];
            if (!$job['process']->isSuccessful() || !is_file($part) || 0 === filesize($part)) {
                @unlink($part);

                return null;
            }
            rename($part, $job['file']);
        }

        return is_file($job['file']) ? (string) file_get_contents($job['file']) : null;
    }
}
