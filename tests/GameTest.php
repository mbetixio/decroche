<?php

namespace App\Tests;

use App\Ai\Brain;
use App\Entity\Seller;
use App\Entity\Training;
use App\Game\Etixio;
use App\Game\Level;
use App\Game\Situations;
use App\Training\Progress;
use App\Training\ScenarioWriter;
use App\Training\Speech;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * Le jeu : une situation tirée au hasard, écrite par un modèle scripté, jouée (au micro ou au clavier), analysée,
 * et qui fait monter de niveau ; le manager crée les comptes et suit chacun.
 */
final class GameTest extends WebTestCase
{
    use Spaces;

    /** @var list<string> */
    private array $script = [];

    /** @var list<string> */
    private array $prompts = [];

    public function testASurpriseCallFromTheDrawToTheAnalysis(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->boot();
        $em = $this->service(EntityManagerInterface::class);
        $camille = $this->member('Camille Roux', 'camille@etixio.test');
        $client->loginUser($camille);
        $etixio = $this->etixio();

        // Le catalogue colle à l'offre : de l'offshore, et pas de promesse qu'Etixio ne tient pas.
        self::assertGreaterThanOrEqual(20, \count(Situations::ALL));
        self::assertStringContainsString('Madagascar', Etixio::OFFER);
        self::assertSame(1, max(array_map(static fn (int $i): int => Situations::draw($etixio, 1)['difficulty'], range(1, 30))) <= 2 ? 1 : 0);
        self::assertSame('annonce', Situations::draw($etixio, 5, 'annonce')['key']);
        // Un client mécontent n'est jamais « détendu » ; les situations jouées à l'instant ne reviennent pas tout de suite.
        self::assertContains(Situations::draw($etixio, 1, 'client_mecontent')['tone'], ['fache', 'decu', 'stresse']);
        $avoid = array_slice(array_keys(Situations::ALL), 0, -1);
        self::assertSame(array_key_last(Situations::ALL), Situations::draw($etixio, 1, null, $avoid)['key']);

        // Nouvel appel : une situation surprise, écrite pour le commercial ; la fiche ne montre rien du brief.
        $client->request('GET', '/');
        self::assertSelectorTextContains('.call-card', 'Nouvel appel');
        $this->script = [$this->situation()];
        $client->request('POST', '/appel/nouveau', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->token($client, 'call')]);
        self::assertResponseIsSuccessful();
        $url = json_decode((string) $client->getResponse()->getContent(), true)['url'];
        $prompt = end($this->prompts);
        self::assertStringContainsString('Madagascar', $prompt);
        self::assertStringContainsString('Nous sommes le', $prompt);
        $crawler = $client->request('GET', $url);
        self::assertSelectorTextContains('.doc', 'Lead développeur Symfony');
        self::assertSelectorTextContains('.objectives', 'Obtenir la visio');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('180 000', $html);
        self::assertStringNotContainsString('Le CTO part en mars', $html);

        // L'appel : le persona sait ce qu'il vit et ce que vend Etixio ; une réplique dite au micro garde sa durée.
        $client->submit($crawler->filter('form[action$="/lancer"]')->form());
        $crawler = $client->followRedirect();
        // Un écran de téléphone, pas un chat : aucun champ de saisie visible, le clavier est un recours caché.
        self::assertSelectorTextContains('.cs-name', 'Julie Masson');
        self::assertSelectorExists('#pickup');
        self::assertSelectorNotExists('textarea');
        self::assertSelectorExists('#keypad[hidden]');
        preg_match('/token = "([^"]+)"/', (string) $client->getResponse()->getContent(), $t);
        $path = $client->getRequest()->getPathInfo();
        $this->script = ['On a déjà essayé l\'offshore, merci.'];
        $client->request('POST', $path.'/message', server: ['HTTP_X_CSRF_TOKEN' => json_decode('"'.$t[1].'"'), 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['message' => 'euh bonjour du coup je vous appelle pour votre annonce qu est-ce qui bloque le recrutement', 'voiceMs' => 6000]));
        self::assertResponseIsSuccessful();
        $prompt = end($this->prompts);
        self::assertStringContainsString('Le CTO part en mars', $prompt);
        self::assertStringContainsString('transcrites automatiquement', $prompt);
        self::assertStringContainsString('pas de période d\'essai gratuite', $prompt);

        // Raccrocher : les objectifs jugés un à un, la note, l'XP, et l'appel en chiffres.
        $this->script = [(string) json_encode([
            'criteria' => array_fill_keys(['decouverte', 'questions', 'ecoute', 'objections', 'valeur', 'closing'], ['score' => 6, 'comment' => 'Correct.']),
            'result' => 'Le client raccroche poliment',
            'objectives' => [['status' => 'atteint', 'evidence' => 'Il a eu la DRH.'], ['status' => 'partiel', 'evidence' => 'À moitié.'], ['status' => 'manqué']],
            'summary' => 'Un premier appel.', 'speech' => 'Trop de tics.', 'tips' => ['Moins de « du coup ».'],
        ])];
        $client->submit($crawler->filter('#finish')->form());
        $client->followRedirect();
        $training = $this->service(EntityManagerInterface::class)->getRepository(Training::class)->findOneBy([]) ?? self::fail();
        self::assertSame(57, $training->score);   // 0,7 × 60 + 30 × (1 + 0,5 + 0) / 3
        self::assertSame('partiel', $training->debrief['goal_reached']);
        self::assertSame(['atteint', 'partiel', 'manqué'], array_column($training->debrief['objectives'], 'status'));
        self::assertSame(71, Level::xp($training));
        self::assertTrue($training->debrief['speech']['voice']);
        self::assertSame(['euh' => 1, 'du coup' => 1], $training->debrief['speech']['filler_words']);
        self::assertSame(1, $training->debrief['speech']['open_questions']);
        self::assertSame(170, $training->debrief['speech']['wpm']);   // 17 mots en 6 secondes
        self::assertSelectorTextContains('.verdict', 'Le client raccroche poliment');
        self::assertSelectorTextContains('.debrief', 'L\'appel en chiffres');
        self::assertSelectorTextContains('.debrief', '+71 XP');
        self::assertSelectorTextContains('.debrief', 'Le CTO part en mars');
    }

    public function testLevelsStreaksAndTheSpeechFigures(): void
    {
        self::assertSame([1, 'Stagiaire', 0], [Level::of(0)['number'], Level::of(0)['name'], Level::of(0)['pct']]);
        self::assertSame([2, 50], [Level::of(275)['number'], Level::of(275)['pct']]);
        self::assertNull(Level::of(9999)['to']);

        $this->boot();
        $em = $this->service(EntityManagerInterface::class);
        $sam = $this->member('Sam Petit', 'sam@etixio.test');
        $scenario = ScenarioWriter::fromArray(json_decode($this->situation(), true));
        $em->persist($scenario);
        $em->flush();
        $trainer = $this->service(Trainer::class);
        $t = $trainer->start($sam, $scenario);
        $this->script = ['Oui.', 'Peut-être.'];
        $trainer->say($t, 'Bonjour, voilà, en fait je voulais vous parler de notre offre, franchement elle est top.');
        $trainer->say($t, 'Comment gérez-vous vos recrutements ? Et vous avez un budget ?');
        $sp = Speech::analyze($t);
        self::assertFalse($sp['voice']);
        self::assertNull($sp['wpm']);
        self::assertSame(2, $sp['questions']);
        self::assertSame(1, $sp['open_questions']);
        self::assertSame(3, $sp['fillers']);
        self::assertGreaterThan(50, $sp['talk_share']);

        $today = new \DateTimeImmutable('2026-11-13 18:00');
        self::assertSame(0, Level::streak([$t], $today));

        // Les missions : un appel au choix noté au moins 0 est réussi ; deux annonces, pas encore ; une reprise ne compte pas.
        $this->script = ['{"criteria": {}, "summary": "Court.", "tips": []}'];
        $trainer->finish($t);
        $em->persist(new \App\Entity\Mission($this->etixio(), null, null, 1, 0, now: new \DateTimeImmutable('2026-11-13 09:00')));
        $em->persist(new \App\Entity\Mission($this->etixio(), $sam, 'annonce', 2, 0, new \DateTimeImmutable('2026-11-20'), 'Préparez le salon', now: new \DateTimeImmutable('2026-11-13 09:00')));
        $em->flush();
        $missions = $this->service(Progress::class)->missions($sam, new \DateTimeImmutable('2026-11-13 12:00'));
        self::assertSame([[0, false, 'annonce'], [1, true, null]], array_map(static fn (array $r): array => [$r['done'], $r['complete'], $r['mission']->situation], $missions));
        $em->persist(\App\Entity\Training::replay($t, 1));
        $em->flush();
        self::assertSame(1, $this->service(Progress::class)->missions($sam)[1]['done']);
    }

    public function testTheManagerCreatesAccountsAndFollowsEachSeller(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->boot();
        $em = $this->service(EntityManagerInterface::class);
        $boss = $this->member('Mathieu Bernard', 'boss@etixio.test', Seller::MANAGER);
        $client->loginUser($boss);

        // Le manager invite Léa : un lien personnel ; elle crée son compte et rejoint l'espace d'Etixio.
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action$="/equipe/inviter"]')->form(), ['email' => 'Lea@Etixio.test']);
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Invitation prête');
        preg_match('#/rejoindre/[a-f0-9]{48}#', (string) $client->getResponse()->getContent(), $link);
        $client->request('GET', '/deconnexion');
        $crawler = $client->request('GET', $link[0]);
        $client->submit($crawler->filter('form')->form(), ['name' => 'Léa Martin', 'password' => 'sparring-test']);
        self::assertResponseRedirects('/moi');
        $lea = $this->service(EntityManagerInterface::class)->getRepository(Seller::class)->findOneBy(['email' => 'lea@etixio.test']) ?? self::fail();
        self::assertSame(Seller::SELLER, $lea->role);
        self::assertSame('Etixio', $lea->space()->name);
        $client->request('GET', '/equipe/'.$lea->id);
        self::assertResponseStatusCodeSame(403);

        // Une autre société : ses membres et ses appels restent chez elle.
        $other = new \App\Entity\Workspace('Autre Boîte');
        $em->persist($other);
        $em->flush();
        $stranger = $this->member('Inconnu', 'inconnu@autre.test', Seller::MANAGER, $other);
        $client->loginUser($boss);
        $client->request('GET', '/equipe/'.$stranger->id);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/equipe');
        self::assertSelectorTextNotContains('table', 'Inconnu');

        // Le manager donne une mission à Léa ; elle la retrouve sur sa page d'accueil.
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action$="/equipe/missions"]')->form(), ['assignee' => (string) $lea->id, 'calls' => '2', 'situation' => 'client_mecontent', 'target' => '75', 'note' => 'Avant le comité de lundi']);
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Mission donnée à Léa Martin');
        self::assertSelectorTextContains('.mission', 'Léa Martin 0/2');
        $lea = $this->service(EntityManagerInterface::class)->getRepository(Seller::class)->findOneBy(['email' => 'lea@etixio.test']) ?? self::fail();
        $lea->profile = 'Commerciale, deux ans en agence.';
        $this->service(EntityManagerInterface::class)->flush();
        $client->loginUser($lea);
        $client->request('GET', '/');
        self::assertSelectorTextContains('.mission-card', 'Client mécontent');
        self::assertSelectorTextContains('.mission-card', 'Avant le comité de lundi');
        $client->loginUser($boss);

        $client->loginUser($boss);
        $client->request('GET', '/equipe/'.$lea->id);
        self::assertSelectorTextContains('h1', 'Léa Martin');
        self::assertSelectorTextContains('.head', 'Stagiaire');
        $stats = $this->service(Progress::class)->of($lea);
        self::assertSame(0, $stats['xp']);
        self::assertSame([], $stats['bySituation']);
        $client->request('GET', '/offre');
        self::assertSelectorTextContains('.offer', 'Madagascar');
    }

    public function testTheSiteAnalysisReadsTheOfferPagesAndStaysOnThePublicWeb(): void
    {
        $this->boot();
        $pages = [
            'https://paie-express.fr/' => '<html><head><title>Paie Express</title><meta name="description" content="La paie en ligne des PME"></head><body><script>var x = 1;</script><h1>La paie sans stress</h1><a href="/offre">Notre offre</a><a href="/blog/recette">Recette</a><a href="https://ailleurs.fr/offre">Ailleurs</a></body></html>',
            'http://127.0.0.1:8300/' => '<html><body>Secret interne</body></html>',
            'https://paie-express.fr/offre' => '<html><body><h2>Nos offres</h2><p>Logiciel de paie, accompagnement DSN, 9 € par bulletin.</p></body></html>',
        ];
        $http = new \Symfony\Component\HttpClient\MockHttpClient(static function (string $method, string $url) use ($pages): \Symfony\Component\HttpClient\Response\MockResponse {
            return isset($pages[$url]) ? new \Symfony\Component\HttpClient\Response\MockResponse($pages[$url], ['response_headers' => ['content-type' => 'text/html']]) : new \Symfony\Component\HttpClient\Response\MockResponse('', ['http_code' => 404]);
        });
        $analyst = new \App\Space\Analyst($this->service(Brain::class), $http);
        $text = $analyst->read('paie-express.fr');
        self::assertStringContainsString('La paie sans stress', $text);
        self::assertStringContainsString('9 € par bulletin', $text);
        self::assertStringNotContainsString('var x', $text);
        self::assertStringNotContainsString('ailleurs.fr', $text);

        // Une adresse interne (le serveur lui-même, le réseau de l'entreprise) n'est jamais lue.
        self::assertSame('', $analyst->read('http://127.0.0.1:8300/'));
        $this->expectException(\InvalidArgumentException::class);
        \App\Space\Analyst::normalize('pas une adresse');
    }

    private function situation(): string
    {
        return (string) json_encode([
            'title' => 'Une annonce de lead dev à Nantes',
            'category' => 'decouverte',
            'context' => 'Vous appelez Julie Masson, DRH de Nautilia, qui recrute un lead développeur depuis trois mois.',
            'material' => "Lead développeur Symfony (H/F)\nNautilia, Nantes\nCDI, 55-65 k€",
            'goal' => 'Décrocher un rendez-vous.',
            'objectives' => ['Atteindre la personne qui porte le besoin', 'Faire émerger le besoin', 'Obtenir la visio de 30 minutes avec le CTO'],
            'persona' => ['name' => 'Julie Masson', 'role' => 'DRH', 'company' => 'Nautilia, logistique, 120 salariés, Nantes', 'personality' => 'Pressée.'],
            'opening' => 'Nautilia, Julie Masson, bonjour ?',
            'brief' => ['setup' => 'Le CTO part en mars et personne ne le sait.', 'budget' => '180 000 € par an.', 'pain' => 'Le poste est vacant depuis trois mois.', 'decision' => 'Le DG.', 'objections' => 'L\'offshore, on a déjà donné.', 'yes' => 'Des profils validés.', 'secret' => 'Le CTO part.'],
        ], \JSON_UNESCAPED_UNICODE);
    }

    private function token(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $id): string
    {
        preg_match('/\'X-CSRF-Token\': "([^"]+)"/', (string) $client->getResponse()->getContent(), $m);

        return json_decode('"'.($m[1] ?? '').'"');
    }

    private function boot(): void
    {
        if (!static::$booted) {
            self::bootKernel();
        }
        Clock::set(new MockClock(new \DateTimeImmutable('2026-11-13 10:00:00')));
        $em = $this->service(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->clear();
        $this->service(Brain::class)->use(MockPlatformFactory::createPlatform(function ($model, $input): TextResult {
            $this->prompts[] = (string) json_encode($input, \JSON_UNESCAPED_UNICODE);

            return new TextResult(array_shift($this->script) ?? 'Hm.');
        }), 'mock');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
