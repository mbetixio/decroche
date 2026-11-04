<?php

namespace App\Space;

use App\Ai\Brain;
use App\Entity\Workspace;
use App\Game\Situations;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * L'analyse d'une société : son site (la page d'accueil et les pages qui parlent de l'offre) et ce qu'elle dit
 * d'elle-même deviennent la fiche d'offre, ses clients types et ses situations d'appel. Le manager corrige ensuite.
 */
final readonly class Analyst
{
    /** Les pages qui parlent de l'offre, repérées à leur adresse ou au texte du lien. */
    private const USEFUL = '/offre|service|solution|produit|product|tarif|prix|pricing|price|a-propos|about|qui-sommes|equipe|expertise|metier|reference|client|cas-|case|faq|accompagnement|prestation|methode|approche/i';

    private HttpClientInterface $http;

    public function __construct(
        private Brain $brain,
        HttpClientInterface $http,
    ) {
        // Une adresse donnée par l'utilisateur : jamais vers le réseau interne.
        $this->http = new NoPrivateNetworkHttpClient($http);
    }

    /**
     * La fiche d'offre, les secteurs clients puis les situations : deux passes du modèle (une minute environ).
     */
    public function analyze(Workspace $space): void
    {
        $site = null !== $space->website && '' !== $space->website ? $this->read($space->website) : '';
        if ('' === trim($site) && mb_strlen(trim($space->about)) < 30) {
            throw new \InvalidArgumentException('Donnez l\'adresse d\'un site lisible, ou décrivez votre société en quelques phrases.');
        }
        $this->offer($space, $site);
        $this->situations($space);
        $space->analyzedAt = new \DateTimeImmutable();
    }

    /**
     * Le texte du site : la page d'accueil et jusqu'à cinq pages sur l'offre, débarrassés du code.
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

        return mb_substr(implode("\n\n", $pages), 0, 30000);
    }

    public static function normalize(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['host']) || !str_contains($parts['host'], '.')) {
            throw new \InvalidArgumentException('Cette adresse de site n\'est pas valide.');
        }

        return $url;
    }

    /**
     * @return array{0: string, 1: list<string>} le texte de la page et les liens utiles du même site
     */
    private function page(string $url): array
    {
        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => 10, 'max_duration' => 20, 'max_redirects' => 3,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Sparring/1.0; analyse d\'offre)', 'Accept-Language' => 'fr,en;q=0.5'],
            ]);
            if (200 !== $response->getStatusCode() || !str_contains($response->getHeaders()['content-type'][0] ?? 'text/html', 'html')) {
                return ['', []];
            }
            $html = mb_substr($response->getContent(), 0, 2_000_000);
        } catch (\Throwable) {
            return ['', []];
        }
        $crawler = new Crawler($html, $url);
        $title = $crawler->filter('title')->count() ? trim($crawler->filter('title')->text('')) : '';
        $description = $crawler->filter('meta[name="description"]')->count() ? trim((string) $crawler->filter('meta[name="description"]')->attr('content')) : '';
        $crawler->filter('script, style, noscript, svg, iframe, template')->each(static function (Crawler $node): void {
            $dom = $node->getNode(0);
            $dom?->parentNode?->removeChild($dom);
        });
        $body = $crawler->filter('body')->count() ? $crawler->filter('body')->text('') : '';
        $text = trim(implode("\n", array_filter([$title, $description, preg_replace('/\s+/u', ' ', $body)])));

        $host = parse_url($url, \PHP_URL_HOST);
        $links = [];
        foreach ($crawler->filter('a[href]')->links() as $link) {
            $href = strtok($link->getUri(), '#') ?: '';
            if (parse_url($href, \PHP_URL_HOST) === $host && rtrim($href, '/') !== rtrim($url, '/') && !preg_match('/\.(pdf|jpe?g|png|gif|webp|zip|svg)$/i', $href)
                && (preg_match(self::USEFUL, $href) || preg_match(self::USEFUL, $link->getNode()->textContent))) {
                $links[$href] = true;
            }
        }

        return [mb_substr($text, 0, 6000), array_keys($links)];
    }

    private function offer(Workspace $space, string $site): void
    {
        $system = <<<'PROMPT'
            Tu prépares un outil d'entraînement commercial : des commerciaux vont s'entraîner au téléphone contre des clients
            simulés. Pour que les situations soient crédibles, tu rédiges la fiche d'offre de leur société, à partir de son site
            et de ce qu'elle dit d'elle-même. Cette fiche servira au client simulé (ce qu'il peut savoir et objecter), au coach
            (qui pénalise toute promesse hors offre) et aux commerciaux.
            N'écris que ce que les sources disent ou impliquent clairement ; quand une information manque (prix, délais…),
            écris « à préciser » plutôt que d'inventer. Aucun tarif inventé.
            Réponds uniquement avec un objet JSON, en français :
            {"name": "le nom de la société",
             "offer": "la fiche, en texte simple avec ces rubriques, chacune sur son paragraphe :\nQui : …\n\nLes offres : (une ligne par offre, commençant par « - »)\n\nLe cadre : délais, prix et façon de chiffrer, démarche commerciale (premier rendez-vous, démo, devis…), engagements\n\nRéférences et preuves : …\n\nCe que la société ne propose pas, ou que rien ne garantit : …\n\nLes objections classiques des prospects : (une ligne par objection, entre guillemets, comme un client la dirait)\n\nLes arguments forts : …",
             "sectors": ["10 à 15 secteurs ou types de clients que la société sert ou vise (en minuscules, ex. « e-commerce », « cabinets d'avocats », « collectivités »)"]}
            PROMPT;
        $user = 'Ce que la société dit d\'elle-même : '.('' !== trim($space->about) ? trim($space->about) : '(rien)')
            ."\n\nSon nom déclaré : {$space->name}"
            ."\n\nSon site".('' !== $site ? " :\n{$site}" : ' : (pas de site lisible)');
        $d = $this->brain->json($system, $user, 0.3);
        $offer = \is_string($d['offer'] ?? null) ? trim($d['offer']) : '';
        if (mb_strlen($offer) < 80) {
            throw new \RuntimeException('L\'analyse n\'a pas abouti : réessayez.');
        }
        $space->offer = $offer;
        $space->sectors = self::strings($d['sectors'] ?? [], 20);
    }

    /**
     * Les situations d'appel de la société : le canevas commun adapté à son offre, plus les siennes.
     */
    public function situations(Workspace $space): void
    {
        $canvas = json_encode(array_map(static fn (array $s): array => ['label' => $s['label'], 'family' => $s['family'], 'direction' => $s['direction'], 'pitch' => $s['pitch']], Situations::GENERIC), \JSON_UNESCAPED_UNICODE);
        $system = <<<PROMPT
            Tu prépares un jeu d'entraînement commercial : à chaque partie, un commercial tire une situation d'appel au hasard
            (un appel qu'il passe ou qu'il reçoit) et la joue contre un client simulé. Écris le catalogue des situations de
            cette société, d'après sa fiche d'offre : 14 à 18 situations variées et réalistes pour SON métier, qui couvrent
            la prospection, la relation client et la négociation. Reprends et adapte les situations du canevas commun qui
            ont du sens pour elle (en réécrivant le pitch avec ses offres, ses clients, ses objections), et ajoute 4 à 6
            situations propres à son métier.
            Le canevas commun : {$canvas}
            Réponds uniquement avec un objet JSON, en français :
            {"situations": [{"key": "identifiant_court_en_snake_case", "label": "titre court", "emoji": "un emoji",
              "family": "prospection, client ou negociation", "direction": "sortant (le commercial appelle) ou entrant (on l'appelle)",
              "material": "le document que le commercial a sous les yeux avant l'appel (« Le message du formulaire », « La fiche client »…)",
              "pitch": "3 ou 4 phrases : qui est en face, ce qu'il veut, ce qui coince, ce qui se cache",
              "objectives": ["trois objectifs concrets et vérifiables pour l'appel"]}]}
            PROMPT;
        $d = $this->brain->json($system, "La société : {$space->name}\n\nSa fiche d'offre :\n{$space->offer}", 0.7);
        $list = [];
        foreach (\is_array($d['situations'] ?? null) ? $d['situations'] : [] as $s) {
            if (null !== $sit = self::situation($s)) {
                $key = substr((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', \is_string($s['key'] ?? null) ? $s['key'] : $sit['label']))), 0, 28) ?: 'situation';
                while (isset($list[$key])) {
                    $key .= '_';
                }
                $list[$key] = $sit;
            }
        }
        if (\count($list) < 5) {
            throw new \RuntimeException('Le catalogue des situations est trop court : réessayez.');
        }
        $space->situations = $list;
    }

    /**
     * Une situation propre, ou null si l'essentiel manque.
     *
     * @return array{label: string, emoji: string, family: string, direction: string, material: string, pitch: string, objectives: list<string>}|null
     */
    public static function situation(mixed $s): ?array
    {
        if (!\is_array($s)) {
            return null;
        }
        $t = static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '';
        $sit = [
            'label' => mb_substr($t($s['label'] ?? ''), 0, 80),
            'emoji' => mb_substr($t($s['emoji'] ?? '') ?: '📞', 0, 4),
            'family' => \in_array($t($s['family'] ?? ''), array_keys(Situations::FAMILIES), true) ? $t($s['family']) : 'prospection',
            'direction' => 'entrant' === $t($s['direction'] ?? '') ? 'entrant' : 'sortant',
            'material' => $t($s['material'] ?? '') ?: 'La fiche',
            'pitch' => $t($s['pitch'] ?? ''),
            'objectives' => \array_slice(self::strings($s['objectives'] ?? [], 4), 0, 3),
        ];

        return '' === $sit['label'] || mb_strlen($sit['pitch']) < 20 || [] === $sit['objectives'] ? null : $sit;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $list, int $max): array
    {
        if (\is_string($list)) {
            $list = preg_split('/\R/', $list) ?: [];
        }

        return \array_slice(array_values(array_unique(array_filter(array_map(static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v, " \t-•") : '', \is_array($list) ? $list : [])))), 0, $max);
    }
}
