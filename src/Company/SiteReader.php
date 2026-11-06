<?php

declare(strict_types=1);

namespace App\Company;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a company's website for the analysis: the home page and up to five pages about the offer (services, pricing,
 * references…), stripped of their code. Only the public web: an address given by a user never reaches the internal
 * network.
 */
final readonly class SiteReader
{
    /** Pages about the offer, spotted by their address or link text. */
    private const string USEFUL = '/offre|service|solution|produit|product|tarif|prix|pricing|price|a-propos|about|qui-sommes|equipe|expertise|metier|reference|client|cas-|case|faq|accompagnement|prestation|methode|approche/i';

    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http)
    {
        $this->http = new NoPrivateNetworkHttpClient($http);
    }

    /**
     * The text of the site, at most 30,000 characters; empty when it cannot be read.
     */
    public function read(string $url): string
    {
        $url = self::normalize($url);
        [$home, $links] = $this->page($url);
        if ('' === $home) {
            return '';
        }
        $pages = ["## {$url}\n{$home}"];
        foreach (\array_slice($links, 0, 5) as $link) {
            [$text] = $this->page($link);
            if ('' !== $text) {
                $pages[] = "## {$link}\n{$text}";
            }
        }

        return mb_substr(implode("\n\n", $pages), 0, 30_000);
    }

    /**
     * An absolute URL for what a user typed ("exemple.fr" becomes "https://exemple.fr").
     *
     * @throws \InvalidArgumentException when it is not a web address
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || !str_contains($host, '.')) {
            throw new \InvalidArgumentException('Cette adresse de site n\'est pas valide.');
        }

        return $url;
    }

    /**
     * @return array{0: string, 1: list<string>} the page's text, and its useful links on the same site
     */
    private function page(string $url): array
    {
        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 20,
                'max_redirects' => 3,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Sparring/1.0; offer analysis)', 'Accept-Language' => 'fr,en;q=0.5'],
            ]);
            if (200 !== $response->getStatusCode() || !str_contains($response->getHeaders()['content-type'][0] ?? 'text/html', 'html')) {
                return ['', []];
            }
            $html = mb_substr($response->getContent(), 0, 2_000_000);
        } catch (\Throwable) {
            return ['', []];
        }
        $crawler = new Crawler($html, $url);
        $title = $crawler->filter('title')->count() > 0 ? trim($crawler->filter('title')->text('')) : '';
        $description = $crawler->filter('meta[name="description"]')->count() > 0 ? trim((string) $crawler->filter('meta[name="description"]')->attr('content')) : '';
        $crawler->filter('script, style, noscript, svg, iframe, template')->each(static function (Crawler $node): void {
            $element = $node->getNode(0);
            $element?->parentNode?->removeChild($element);
        });
        $body = $crawler->filter('body')->count() > 0 ? $crawler->filter('body')->text('') : '';
        $text = trim(implode("\n", array_filter([$title, $description, (string) preg_replace('/\s+/u', ' ', $body)])));

        $host = parse_url($url, \PHP_URL_HOST);
        $links = [];
        foreach ($crawler->filter('a[href]')->links() as $link) {
            $href = strtok($link->getUri(), '#') ?: '';
            if (parse_url($href, \PHP_URL_HOST) === $host
                && rtrim($href, '/') !== rtrim($url, '/')
                && !preg_match('/\.(pdf|jpe?g|png|gif|webp|zip|svg)$/i', $href)
                && (preg_match(self::USEFUL, $href) || preg_match(self::USEFUL, $link->getNode()->textContent))) {
                $links[$href] = true;
            }
        }

        return [mb_substr($text, 0, 6000), array_keys($links)];
    }
}
