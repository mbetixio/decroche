<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\EdgeTtsSynthesizer;
use App\Voice\PocketTtsSynthesizer;
use App\Voice\Prosody;
use App\Voice\Synthesizers;
use App\Voice\VoiceCachePruner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Each voice goes to the synthesizer that speaks it: Pocket TTS for "pocket:…", edge-tts for Edge's voices; either one
 * running is enough.
 */
final class SynthesizersTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/decroche-synths-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir.'/edge-tts', "#!/bin/sh\nwhile [ \$# -gt 0 ]; do [ \"\$1\" = --write-media ] && out=\"\$2\"; shift; done\nprintf MP3 > \"\$out\"\n");
        chmod($this->dir.'/edge-tts', 0o755);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testEachVoiceGoesToItsSynthesizer(): void
    {
        $synthesizers = $this->synthesizers('http://pocket:8100', [new MockResponse('{}'), new MockResponse('RIFF wav')]);

        self::assertTrue($synthesizers->isAvailable());
        self::assertSame('RIFF wav', $synthesizers->speak('Hello.', 'pocket:jane', new Prosody()));
        $edge = $synthesizers->start('Hello.', 'en-US-AvaNeural', new Prosody());
        $edge->wait();
        self::assertSame('MP3', $edge->audio());
    }

    public function testEdgeAloneIsEnough(): void
    {
        self::assertTrue($this->synthesizers('', [])->isAvailable());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function synthesizers(string $url, array $responses): Synthesizers
    {
        $pruner = new VoiceCachePruner($this->dir.'/voices');

        return new Synthesizers(
            new PocketTtsSynthesizer(new MockHttpClient($responses), $url, 'http://app/voices', $this->dir.'/voices', $pruner, 1000000),
            new EdgeTtsSynthesizer($this->dir.'/edge-tts', $this->dir.'/voices', $pruner, 1000000),
        );
    }
}
