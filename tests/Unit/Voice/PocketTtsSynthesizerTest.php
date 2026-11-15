<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\PocketTtsSynthesizer;
use App\Voice\Prosody;
use App\Voice\SynthesisException;
use App\Voice\VoiceCachePruner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The Pocket TTS synthesizer, talking to a stand-in for its server: the health check, the requests it sends (a built-in
 * voice by name, an exported one by URL), the cache, and the failures the browser's voice then covers.
 */
final class PocketTtsSynthesizerTest extends TestCase
{
    private const string WAV = "RIFF\x24\x00\x00\x00WAVEfmt ";

    private string $dir;

    /** @var list<array{method: string, url: string, body: string}> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/decroche-pocket-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testALineIsAskedOfTheServerThenServedFromTheCache(): void
    {
        // The WAV arrives after a pause: asking whether it is done does not wait for it.
        $tts = $this->synthesizer([new MockResponse('{"status":"healthy"}'), new MockResponse(['', self::WAV])]);
        self::assertTrue($tts->isAvailable());

        $synthesis = $tts->start('Hello there.', 'pocket:jane', new Prosody());
        while (!$synthesis->isDone()) {
            usleep(1000);
        }
        self::assertSame(self::WAV, $synthesis->audio());
        self::assertSame(['GET http://pocket:8100/health', 'POST http://pocket:8100/tts'], array_map(static fn (array $r): string => $r['method'].' '.$r['url'], $this->requests));
        parse_str($this->requests[1]['body'], $body);
        self::assertSame(['text' => 'Hello there.', 'voice_url' => 'jane'], $body);

        // Heard again: from the cache, at once, without asking the server.
        $again = $tts->start('Hello there.', 'pocket:jane', new Prosody());
        self::assertTrue($again->isDone());
        $again->wait();
        self::assertSame(self::WAV, $again->audio());
        self::assertSame(self::WAV, $tts->speak('Hello there.', 'pocket:jane', new Prosody()));
        self::assertCount(2, $this->requests);
    }

    public function testAnExportedVoiceIsFetchedFromTheApp(): void
    {
        $tts = $this->synthesizer([new MockResponse('{}'), new MockResponse(self::WAV)]);

        self::assertSame(self::WAV, $tts->speak('Bonjour.', 'pocket:fr/f-2154_2576_000020-0003', new Prosody()));
        parse_str($this->requests[1]['body'], $body);
        self::assertSame('http://app/voices/fr/f-2154_2576_000020-0003.safetensors', $body['voice_url']);
    }

    public function testAFailedSynthesisGivesNoAudio(): void
    {
        $tts = $this->synthesizer([
            new MockResponse('{}'),
            new MockResponse('{"detail":"no"}', ['http_code' => 400]),
            new MockResponse('not a wav'),
            new MockResponse([new TransportException('cut')]),
            new MockResponse([new TransportException('cut')]),
            new MockResponse('{"detail":"no"}', ['http_code' => 500]),
        ]);

        $refused = $tts->start('First.', 'pocket:jane', new Prosody());
        $refused->wait();
        self::assertNull($refused->audio());
        self::assertNull($tts->start('Second.', 'pocket:jane', new Prosody())->audio());

        $cut = $tts->start('Third.', 'pocket:jane', new Prosody());
        while (!$cut->isDone()) {
            usleep(1000);
        }
        self::assertNull($cut->audio());
        $cutAgain = $tts->start('Fourth.', 'pocket:jane', new Prosody());
        $cutAgain->wait();
        self::assertNull($cutAgain->audio());
        self::assertSame([], glob($this->dir.'/voices/*'), 'Nothing is cached.');

        $this->expectException(SynthesisException::class);
        $tts->speak('Fifth.', 'pocket:jane', new Prosody());
    }

    public function testWithoutAServerTheOtherVoicesSpeak(): void
    {
        self::assertFalse($this->synthesizer([], url: '')->isAvailable());
        self::assertFalse($this->synthesizer([new MockResponse('', ['http_code' => 503])])->isAvailable());
        $down = $this->synthesizer([new MockResponse('', ['error' => 'refused'])]);
        self::assertFalse($down->isAvailable());

        $this->expectExceptionMessage('not running');
        $down->start('Hello.', 'pocket:jane', new Prosody());
    }

    public function testAServerBusySayingACallStillRuns(): void
    {
        // Late to answer while it speaks: the call keeps its voice rather than switch to Edge's in the middle.
        $busy = new PocketTtsSynthesizer(new MockHttpClient(static fn (): never => throw new TimeoutException('Idle timeout reached.')), 'http://pocket:8100', 'http://app/voices', $this->dir.'/voices', new VoiceCachePruner($this->dir.'/voices'));

        self::assertTrue($busy->isAvailable());
    }

    public function testTheServerIsAskedAgainForEachRequest(): void
    {
        $tts = $this->synthesizer([new MockResponse('', ['http_code' => 503]), new MockResponse('{}')]);

        self::assertFalse($tts->isAvailable());
        self::assertFalse($tts->isAvailable(), 'asked once per request');
        $tts->reset();
        self::assertTrue($tts->isAvailable());
    }

    public function testTheCacheIsPrunedNowAndThen(): void
    {
        mkdir($this->dir.'/voices');
        $old = $this->dir.'/voices/old.wav';
        file_put_contents($old, self::WAV);
        touch($old, time() - 90 * 86400);

        $this->synthesizer([new MockResponse('{}'), new MockResponse(self::WAV)], pruneOneIn: 1)->speak('A new line.', 'pocket:jane', new Prosody());

        self::assertFileDoesNotExist($old);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'nothing to say' => ['   ', 'pocket:jane'];
        yield 'an Edge voice' => ['Hello.', 'en-US-AvaNeural'];
        yield 'a path out of the voices' => ['Hello.', 'pocket:../../etc/passwd'];
        yield 'a URL' => ['Hello.', 'pocket:https://example.com/voice'];
    }

    #[DataProvider('refusals')]
    public function testItRefusesWhatItCannotSay(string $text, string $voice): void
    {
        $this->expectException(SynthesisException::class);
        $this->synthesizer([new MockResponse('{}')])->start($text, $voice, new Prosody());
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function synthesizer(array $responses, string $url = 'http://pocket:8100', int $pruneOneIn = 1000000): PocketTtsSynthesizer
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

            return array_shift($responses) ?? throw new \LogicException('No more responses.');
        });

        return new PocketTtsSynthesizer($http, $url, 'http://app/voices/', $this->dir.'/voices', new VoiceCachePruner($this->dir.'/voices'), $pruneOneIn);
    }
}
