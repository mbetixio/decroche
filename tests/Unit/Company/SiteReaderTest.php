<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company;

use App\Company\SiteReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SiteReaderTest extends TestCase
{
    public function testItReadsTheHomeAndTheOfferPagesWithoutTheirCode(): void
    {
        // A public address written as an IP: the private-network guard lets it through without a DNS lookup.
        $pages = [
            'https://93.184.215.14/' => '<html><head><title>Acme</title><meta name="description" content="Software for retailers"></head><body>
                <script>track("secret")</script><h1>We build stores</h1>
                <a href="/services">Our services</a> <a href="/blog/2024">Blog</a> <a href="https://other.example/pricing">Partner</a>
                <a href="/brochure.pdf">Brochure</a></body></html>',
            'https://93.184.215.14/services' => '<html><body><style>.x{}</style><p>Dedicated teams and audits.</p></body></html>',
        ];
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url) use ($pages, &$asked): MockResponse {
            $asked[] = $url;

            return new MockResponse($pages[$url] ?? '', ['http_code' => isset($pages[$url]) ? 200 : 404, 'response_headers' => ['content-type' => 'text/html']]);
        });

        $text = new SiteReader($http)->read('93.184.215.14');

        self::assertStringContainsString('Acme', $text);
        self::assertStringContainsString('Software for retailers', $text);
        self::assertStringContainsString('We build stores', $text);
        self::assertStringContainsString('Dedicated teams and audits.', $text);
        self::assertStringNotContainsString('secret', $text, 'Scripts are left out.');
        self::assertSame(['https://93.184.215.14/', 'https://93.184.215.14/services'], $asked, 'Only the offer pages of the same site are read.');
    }

    public function testABotCheckReadsAsAnUnreadableSite(): void
    {
        $challenge = '<html><head><title>Just a moment...</title></head><body><p>Enable JavaScript and cookies to continue</p><script src="/cdn-cgi/challenge-platform/h/b/orchestrate/chl_page/v1"></script></body></html>';
        $blocked = new MockHttpClient(new MockResponse($challenge, ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']]));
        $forbidden = new MockHttpClient(new MockResponse('<html><body>Sorry, you have been blocked</body></html>', ['http_code' => 403, 'response_headers' => ['content-type' => 'text/html']]));

        self::assertSame('', new SiteReader($blocked)->read('93.184.215.14'), 'A challenge page served with a 200 is not the company\'s text.');
        self::assertSame('', new SiteReader($forbidden)->read('93.184.215.14'));
    }

    public function testAnAddressOnThePrivateNetworkIsNeverReached(): void
    {
        $asked = 0;
        $http = new MockHttpClient(static function () use (&$asked): MockResponse {
            ++$asked;

            return new MockResponse('<html><body>Internal admin</body></html>', ['response_headers' => ['content-type' => 'text/html']]);
        });

        $reader = new SiteReader($http);

        self::assertSame('', $reader->read('http://127.0.0.1/admin'));
        self::assertSame('', $reader->read('http://192.168.1.10/'));
        self::assertSame('', $reader->read('http://169.254.169.254/latest/meta-data'));
        self::assertSame(0, $asked);
    }

    public function testAnAddressIsCompletedOrRefused(): void
    {
        self::assertSame('https://acme.example', SiteReader::normalize(' acme.example '));
        self::assertSame('http://acme.example/about', SiteReader::normalize('http://acme.example/about'));

        $this->expectExceptionMessage('company.error.invalid_website');
        SiteReader::normalize('localhost');
    }
}
