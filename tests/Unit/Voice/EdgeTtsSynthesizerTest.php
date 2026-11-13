<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\EdgeTtsSynthesizer;
use App\Voice\Prosody;
use App\Voice\SynthesisException;
use App\Voice\VoiceCachePruner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The real synthesizer, driving a stand-in for the edge-tts command: a script that writes the MP3 it is asked for, or
 * fails, or writes nothing, as the "mode" file next to it says.
 */
final class EdgeTtsSynthesizerTest extends TestCase
{
    private string $dir;
    private string $binary;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/decroche-tts-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->binary = $this->dir.'/edge-tts';
        file_put_contents($this->binary, <<<'SH'
            #!/bin/sh
            # Writes "MP3 <text>" to the file after --write-media; with "fail" in ./mode it exits in error, with "empty" it
            # writes nothing.
            mode=$(cat "$(dirname "$0")/mode" 2>/dev/null); out=""; text=""
            # "flaky": the first try fails, the next works.
            if [ "$mode" = "flaky" ]; then echo ok > "$(dirname "$0")/mode"; exit 1; fi
            while [ $# -gt 0 ]; do
                case "$1" in
                    --write-media) out="$2"; shift ;;
                    --text) text="$2"; shift ;;
                esac
                shift
            done
            [ "$mode" = "fail" ] && exit 1
            [ "$mode" = "empty" ] && : > "$out" && exit 0
            printf 'MP3 %s' "$text" > "$out"
            SH);
        chmod($this->binary, 0o755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testALineIsSynthesizedThenServedFromTheCache(): void
    {
        $tts = $this->synthesizer();
        self::assertTrue($tts->isAvailable());

        $synthesis = $tts->start('Hello there.', 'en-US-AvaNeural', new Prosody());
        $synthesis->wait();
        self::assertTrue($synthesis->isDone());
        self::assertSame('MP3 Hello there.', $synthesis->audio());

        // Heard again: from the cache, at once.
        $this->mode('fail');
        $again = $tts->start('Hello there.', 'en-US-AvaNeural', new Prosody());
        self::assertTrue($again->isDone());
        self::assertSame('MP3 Hello there.', $again->audio());
        self::assertSame('MP3 Hello there.', $tts->speak('Hello there.', 'en-US-AvaNeural', new Prosody()));
    }

    public function testAFailedOrEmptySynthesisGivesNoAudio(): void
    {
        $tts = $this->synthesizer();

        $this->mode('fail');
        $failed = $tts->start('First line.', 'en-US-AvaNeural', new Prosody());
        $failed->wait();
        self::assertNull($failed->audio());

        $this->mode('empty');
        $empty = $tts->start('Second line.', 'en-US-AvaNeural', new Prosody());
        $empty->wait();
        self::assertNull($empty->audio());
        self::assertSame([], glob($this->dir.'/voices/*.part'), 'No half-written file is left.');

        $this->expectException(SynthesisException::class);
        $tts->speak('Third line.', 'en-US-AvaNeural', new Prosody());
    }

    public function testARefusedSynthesisIsTriedOnceMore(): void
    {
        $this->mode('flaky');
        $synthesis = $this->synthesizer()->start('Try again.', 'en-US-AvaNeural', new Prosody());
        $synthesis->wait();

        self::assertSame('MP3 Try again.', $synthesis->audio());
    }

    public function testTheCacheIsPrunedNowAndThen(): void
    {
        mkdir($this->dir.'/voices');
        $old = $this->dir.'/voices/old.mp3';
        file_put_contents($old, 'MP3');
        touch($old, time() - 90 * 86400);

        // Once in one: the pruning runs before this new line.
        $this->synthesizer(pruneOneIn: 1)->start('A new line.', 'fr-FR-DeniseNeural', new Prosody())->wait();

        self::assertFileDoesNotExist($old);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'nothing to say' => ['   ', 'en-US-AvaNeural'];
        yield 'an unknown voice' => ['Hello.', 'en-US-Ava; rm -rf /'];
    }

    #[DataProvider('refusals')]
    public function testItRefusesWhatItCannotSay(string $text, string $voice): void
    {
        $this->expectException(SynthesisException::class);
        $this->synthesizer()->start($text, $voice, new Prosody());
    }

    public function testWithoutEdgeTtsTheBrowserSpeaks(): void
    {
        $tts = new EdgeTtsSynthesizer($this->dir.'/missing', $this->dir.'/voices', new VoiceCachePruner($this->dir.'/voices'));
        self::assertFalse($tts->isAvailable());

        $this->expectExceptionMessage('edge-tts is not installed');
        $tts->start('Hello.', 'en-US-AvaNeural', new Prosody());
    }

    private function mode(string $mode): void
    {
        file_put_contents($this->dir.'/mode', $mode);
    }

    private function synthesizer(int $pruneOneIn = 1_000_000): EdgeTtsSynthesizer
    {
        return new EdgeTtsSynthesizer($this->binary, $this->dir.'/voices', new VoiceCachePruner($this->dir.'/voices'), $pruneOneIn);
    }
}
