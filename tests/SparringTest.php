<?php

namespace App\Tests;

use App\Ai\Brain;
use App\Entity\Scenario;
use App\Entity\Seller;
use App\Entity\Training;
use App\Training\Coach;
use App\Training\PersonaTools;
use App\Training\Progress;
use App\Training\ScenarioWriter;
use App\Training\Trainer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Yaml\Yaml;

/**
 * Des sessions jouées de bout en bout avec un modèle scripté : chaque réponse est la ligne suivante du script, et le
 * prompt reçu est gardé pour vérifier ce que le modèle savait.
 */
final class SparringTest extends WebTestCase
{
    use Spaces;

    /** @var list<string> */
    private array $script = [];

    /** @var list<string> */
    private array $prompts = [];

    private MockClock $clock;

    public function testASessionFromTheFirstWordToTheDebrief(): void
    {
        $this->boot();
        [$camille] = $this->sellers();
        $scenario = $this->scenario();
        $trainer = $this->service(Trainer::class);

        $training = $trainer->start($camille, $scenario);
        self::assertSame([Training::PERSONA], array_column($training->messages, 'role'));
        self::assertSame(480, $training->secondsLeft($this->clock->now()));

        // Le persona a son brief et ses règles ; ce qu'il dit n'en est pas une copie.
        // Une seule passe du modèle : le repère [devoiler:…] note la rubrique et disparaît du texte, le nom aussi.
        $this->script = ['[devoiler:pain] Bruno Lefèvre : La DSN a six mois de retard, voilà.'];
        $r = $trainer->say($training, 'Qu\'est-ce qui vous fait chercher du renfort aujourd\'hui ?');
        self::assertSame('La DSN a six mois de retard, voilà.', $r['reply']);
        self::assertSame(['pain'], $training->revealed);

        $prompt = end($this->prompts);
        self::assertStringContainsString('400 000 €', $prompt);
        self::assertStringContainsString('commence ta réponse par le repère [devoiler:clé]', $prompt);
        self::assertFalse($r['ended']);

        // Ses gestes : lâcher une rubrique la note sur la session (une seule fois).
        $tools = new PersonaTools($training);
        $tools->reveal('pain');
        $tools->reveal('pain');
        self::assertStringContainsString('inconnue', $tools->reveal('mood'));
        self::assertSame(['pain'], $training->revealed);
        self::assertStringContainsString('(déjà dévoilé', $this->service(\App\Training\Persona::class)->system($training));

        // Le débrief : six critères, une note sur 100, le brief dévoilé, des citations vraiment dites.
        $this->script = [json_encode([
            'criteria' => ['decouverte' => ['score' => 8, 'comment' => 'Bonne question d\'ouverture.'], 'questions' => ['score' => 7], 'ecoute' => ['score' => 6], 'objections' => ['score' => 3], 'valeur' => ['score' => 4], 'closing' => ['score' => 2]],
            'summary' => 'Un début prometteur.',
            'next_step' => false,
            'discovered' => ['decision'],
            'missed' => [['rubric' => 'budget', 'what' => 'Les 400 000 €', 'how' => 'Quel budget avez-vous prévu ?'], ['rubric' => 'pain', 'what' => 'déjà trouvé', 'how' => '']],
            'best' => [['quote' => 'qu’est-ce qui vous fait chercher du renfort aujourd’hui', 'why' => 'Ouverte.'], ['quote' => 'Une phrase jamais dite', 'why' => 'Inventée.']],
            'worst' => [],
            'tips' => ['Un', 'Deux', 'Trois', 'Quatre'],
        ], \JSON_UNESCAPED_UNICODE)];
        $trainer->finish($training);
        self::assertSame(Training::DONE, $training->status);
        self::assertSame('seller', $training->endedBy);
        self::assertSame(50, $training->score);
        $d = $training->debrief;
        self::assertSame('Bonne question d\'ouverture.', $d['criteria']['decouverte']['comment']);
        self::assertSame(['pain', 'decision'], $training->revealed);
        self::assertSame(['budget'], array_column($d['missed'], 'rubric'));
        self::assertCount(1, $d['best']);
        self::assertCount(3, $d['tips']);
        self::assertStringContainsString('COMMERCIAL : Qu\'est-ce qui vous fait chercher', end($this->prompts));
        self::assertSame([true, true], [$training->briefSheet()[1]['found'], $training->briefSheet()[2]['found']]);

        $stats = $this->service(Progress::class)->of($camille);
        self::assertSame(1, $stats['done']);
        self::assertSame(50, $stats['average']);
        self::assertSame('closing', $stats['weakest']);
        self::assertSame('decouverte', $stats['strongest']);
    }

    public function testTheClockAndThePersonaEndTheMeeting(): void
    {
        $this->boot();
        [$camille] = $this->sellers();
        $trainer = $this->service(Trainer::class);
        $training = $trainer->start($camille, $this->scenario());

        // Le temps passe : la réplique de trop n'est pas transmise, la session attend son débrief.
        $this->clock->sleep(8 * 60 + 1);
        try {
            $trainer->say($training, 'Encore une question ?');
            self::fail('Le temps est écoulé.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('terminée', $e->getMessage());
        }
        self::assertSame(Training::DEBRIEFING, $training->status);
        self::assertSame('clock', $training->endedBy);
        self::assertCount(1, $training->messages);

        // Le persona raccroche de lui-même : le repère [fin] arrête la session, et ne s'entend pas.
        $other = $trainer->start($camille, $this->scenario());
        $this->script = ['Très bien, on se rappelle jeudi. Au revoir. [fin]'];
        $r = $trainer->say($other, 'Je vous rappelle jeudi alors ?');
        self::assertSame('Très bien, on se rappelle jeudi. Au revoir.', $r['reply']);
        self::assertTrue($r['ended']);
        self::assertSame('persona', $other->endedBy);
        $this->script = ['{"criteria": {}, "summary": "Court.", "tips": []}'];
        $trainer->finish($other);

        // Sans une réplique du commercial, le débrief ne demande rien au modèle.
        $count = \count($this->prompts);
        $trainer->finish($training);
        self::assertSame(0, $training->score);
        self::assertCount($count, $this->prompts);

        // Abandonner : pas de note, et la session suivante repart de zéro.
        $next = $trainer->start($camille, $this->scenario());
        $trainer->abandon($next);
        self::assertSame(Training::ABANDONED, $next->status);
        self::assertNull($trainer->current($camille));
        $this->expectException(\InvalidArgumentException::class);
        $trainer->abandon($next);
    }

    public function testTheLibraryAndTheScenarioWriter(): void
    {
        $this->boot();
        $library = Yaml::parseFile(\dirname(__DIR__).'/config/scenarios.yaml');
        self::assertGreaterThanOrEqual(6, \count($library));
        foreach ($library as $d) {
            $s = ScenarioWriter::fromArray($d);
            self::assertArrayHasKey($s->category, Scenario::CATEGORIES);
            self::assertCount(6, array_filter($s->brief), $s->title);
        }

        $this->script = ['```json'."\n".json_encode(['title' => 'Le DSI de Transalp', 'category' => 'n_importe_quoi', 'context' => 'Vous rencontrez Pierre.', 'goal' => 'Un atelier.', 'persona' => ['name' => 'Pierre Vidal', 'role' => 'DSI', 'company' => 'Transalp', 'personality' => 'Fier.'], 'opening' => 'Bonjour.', 'brief' => ['budget' => '200 k€', 'pain' => 'Le prestataire ferme.', 'objections' => ['Trop cher', 'Trop long']]])."\n```"];
        [$camille] = $this->sellers();
        $s = $this->service(ScenarioWriter::class)->write('Transalp, transporteur frigorifique, prestataire qui ferme en juin.', $camille, 'decouverte', 3, 15);
        self::assertSame('decouverte', $s->category);
        self::assertSame(3, $s->difficulty);
        self::assertSame('Trop cher ; Trop long', $s->brief['objections']);
        self::assertSame($camille, $s->author);
        self::assertStringContainsString('Transalp', end($this->prompts));
        self::assertSame('Etixio', $s->workspace?->name);

        // Depuis la transcription d'un vrai appel : le client est reconstitué tel qu'il était, pour rejouer l'appel.
        $this->script = [(string) json_encode(['title' => 'Le rappel de Transalp', 'context' => 'Vous rappelez Pierre.', 'goal' => 'Un atelier.', 'persona' => ['name' => 'Pierre Vidal', 'gender' => 'm', 'role' => 'DSI', 'company' => 'Transalp', 'personality' => 'Fier.'], 'opening' => 'Vidal.', 'brief' => ['pain' => 'Le prestataire ferme.']])];
        $call = $this->service(ScenarioWriter::class)->write("Client : Vidal.\nCommercial : Bonjour, c'est Etixio…", $camille, call: true);
        self::assertStringContainsString('transcription d\'un vrai appel', (string) end($this->prompts));
        self::assertSame('m', $call->persona['gender'] ?? null);

        // Un scénario tiré d'une fiche prospect reste à son auteur et aux managers.
        [, $sam, $boss] = $this->sellers();
        self::assertTrue($s->visibleTo($camille));
        self::assertFalse($s->visibleTo($sam));
        self::assertTrue($s->visibleTo($boss));
        self::assertTrue($this->scenario()->visibleTo($sam));

        $this->expectException(\InvalidArgumentException::class);
        $this->service(ScenarioWriter::class)->write('trop court');
    }

    public function testTheWebsiteFromRegistrationToTheTeamBoard(): void
    {
        $client = $this->client();
        $client->request('GET', '/');
        self::assertResponseRedirects('/connexion');

        // Une société ouvre son espace : son manager est conduit à l'analyse de son offre avant tout le reste.
        $crawler = $client->request('GET', '/inscription');
        $client->submit($crawler->filter('form')->form(), ['company' => 'Paie Express', 'name' => 'Mathieu Bernard', 'email' => 'mathieu@etixio.test', 'password' => 'sparring-test']);
        self::assertResponseRedirects('/espace');
        $client->request('GET', '/');
        self::assertResponseRedirects('/espace');
        $crawler = $client->followRedirect();
        $this->script = [
            json_encode(['name' => 'Paie Express', 'offer' => "Qui : un éditeur de logiciel de paie pour les PME.\n\nLes offres :\n- Le logiciel de paie en ligne\n- L'accompagnement DSN\n\nCe que la société ne propose pas : pas de paie à façon.", 'sectors' => ['PME du BTP', 'cabinets comptables']]),
            json_encode(['situations' => [
                ['key' => 'dsn_bloquee', 'label' => 'La DSN est bloquée', 'emoji' => '🧾', 'family' => 'client', 'direction' => 'entrant', 'material' => 'Le ticket du client', 'pitch' => 'Un client appelle : sa DSN est rejetée depuis deux mois, il menace de partir.', 'objectives' => ['Comprendre', 'Rassurer', 'Plan d\'action']],
                ['key' => 'demande_prix', 'label' => 'Demande de prix', 'emoji' => '💶', 'family' => 'prospection', 'direction' => 'sortant', 'material' => 'Le formulaire', 'pitch' => 'Un cabinet comptable veut un prix pour 40 dossiers de paie.', 'objectives' => ['Découvrir', 'Pas de prix au hasard', 'Démo']],
                ['key' => 'concurrent', 'label' => 'Le concurrent moins cher', 'emoji' => '⚔️', 'family' => 'negociation', 'direction' => 'entrant', 'material' => 'La proposition', 'pitch' => 'Le prospect a une offre concurrente moins chère et hésite.', 'objectives' => ['Comprendre', 'Valeur', 'Garder l\'affaire']],
                ['key' => 'renouvellement', 'label' => 'Renouvellement', 'emoji' => '🔁', 'family' => 'negociation', 'direction' => 'entrant', 'material' => 'Le contrat', 'pitch' => 'Les achats veulent une remise de 20 % au renouvellement.', 'objectives' => ['Valeur', 'Contrepartie', 'Renouveler']],
                ['key' => 'suivi', 'label' => 'Suivi client', 'emoji' => '🩺', 'family' => 'client', 'direction' => 'sortant', 'material' => 'La fiche client', 'pitch' => 'Le commercial appelle un client pour faire le point, un irritant couve.', 'objectives' => ['Irritant', 'Risque', 'Action']],
            ]], \JSON_UNESCAPED_UNICODE),
        ];
        $client->submit($crawler->filter('form[action$="/espace/analyser"]')->form(), ['about' => 'Nous éditons un logiciel de paie en ligne pour les PME et les cabinets comptables.']);
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Analyse terminée');
        $em = $this->service(EntityManagerInterface::class);
        $space = $em->getRepository(\App\Entity\Workspace::class)->findOneBy(['name' => 'Paie Express']) ?? self::fail();
        self::assertTrue($space->isReady());
        self::assertSame(['dsn_bloquee', 'demande_prix', 'concurrent', 'renouvellement', 'suivi'], array_keys($space->situations));
        self::assertStringContainsString('logiciel de paie', (string) end($this->prompts));

        // Le manager corrige la fiche, retire une situation, en ajoute une ; puis il invite Camille.
        $form = $client->getCrawler()->filter('form:not(.analyze)')->form();
        $client->submit($form, ['offer' => $space->offer."\n\nLes arguments forts : la DSN sans rejet.", 'situations[suivi][remove]' => '1', 'situations[_new][label]' => 'Le standard fait barrage', 'situations[_new][pitch]' => 'L\'assistante du DAF filtre les appels de démarchage.', 'situations[_new][objectives]' => "Alliée\nCréneau", 'criteria[closing][label]' => '', 'criteria[nouveau_1][label]' => 'Qualification BANT', 'criteria[nouveau_1][hint]' => 'budget, autorité, besoin, délai']);
        $em->clear();
        $space = $em->getRepository(\App\Entity\Workspace::class)->findOneBy(['name' => 'Paie Express']) ?? self::fail();
        self::assertStringContainsString('la DSN sans rejet', $space->offer);
        self::assertArrayNotHasKey('suivi', $space->situations);
        self::assertSame('Le standard fait barrage', end($space->situations)['label']);
        self::assertSame(['decouverte', 'questions', 'ecoute', 'objections', 'valeur', 'qualification_bant'], array_keys($space->scorecard()));
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('form[action$="/equipe/inviter"]')->form(), ['email' => 'Camille@Etixio.test']);
        $client->followRedirect();
        preg_match('#/rejoindre/[a-f0-9]{48}#', (string) $client->getResponse()->getContent(), $link);
        self::assertNotEmpty($link);

        // Camille rejoint l'espace par le lien, se présente, puis joue.
        $client->request('GET', '/deconnexion');
        $crawler = $client->request('GET', $link[0]);
        self::assertSelectorTextContains('h2', 'Paie Express');
        $client->submit($crawler->filter('form')->form(), ['name' => 'Camille Roux', 'password' => 'sparring-test']);
        self::assertResponseRedirects('/moi');
        $client->request('GET', '/');
        self::assertResponseRedirects('/moi');
        $crawler = $client->followRedirect();
        $client->submit($crawler->filter('form')->form(), ['profile' => 'Commerciale junior, je vends surtout aux cabinets comptables.']);
        self::assertResponseRedirects('/');
        $client->request('GET', $link[0]);
        self::assertResponseStatusCodeSame(404);
        $sellers = $em->getRepository(Seller::class)->findBy([], ['id' => 'ASC']);
        self::assertSame([Seller::MANAGER, Seller::SELLER], array_map(static fn (Seller $s): string => $s->role, $sellers));
        self::assertSame($sellers[0]->workspace, $sellers[1]->workspace);
        $scenario = $this->scenario($em->find(\App\Entity\Workspace::class, $space->id));

        // Camille s'entraîne sur un scénario de la bibliothèque : la page de la session ne montre jamais le brief.
        $crawler = $client->request('GET', '/bibliotheque');
        self::assertSelectorTextContains('.scenario-card h3', $scenario->title);
        $client->submit($crawler->filter('.scenario-card form')->form());
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSame($scenario->opening, json_decode($crawler->filter('#last-line')->text(), true)['line']);
        self::assertStringNotContainsString('400 000', (string) $client->getResponse()->getContent());
        preg_match('#/sessions/(\d+)$#', $client->getRequest()->getPathInfo(), $m);
        preg_match('/token = "([^"]+)"/', (string) $client->getResponse()->getContent(), $t);
        $this->script = ['Six mois de retard sur la DSN.'];
        $client->request('POST', '/sessions/'.$m[1].'/message', server: ['HTTP_X_CSRF_TOKEN' => json_decode('"'.$t[1].'"'), 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['message' => 'Quel est votre projet ?']));
        self::assertResponseIsSuccessful();
        self::assertSame('Six mois de retard sur la DSN.', json_decode((string) $client->getResponse()->getContent(), true)['reply']);
        $client->request('POST', '/sessions/'.$m[1].'/message', server: ['HTTP_X_CSRF_TOKEN' => 'faux', 'CONTENT_TYPE' => 'application/json'], content: '{"message": "x"}');
        self::assertResponseStatusCodeSame(403);

        $this->script = ['{"criteria": {"decouverte": {"score": 6, "comment": "Une question."}}, "summary": "Court.", "tips": ["Creusez le budget."]}'];
        $client->submit($crawler->filter('#finish')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.ring', '10');
        self::assertSelectorTextContains('.debrief', '400 000 €');
        self::assertSelectorTextContains('.tips', 'Creusez le budget.');
        self::assertSelectorTextContains('.transcript', 'Quel est votre projet ?');
        // Le coach a noté sur la grille de la société : la qualification BANT, plus de closing.
        $coach = (string) end($this->prompts);
        self::assertStringContainsString('Qualification BANT', $coach);
        self::assertStringNotContainsString('Closing / prochaine étape', $coach);

        // Rejouer ce moment : un nouvel appel repart juste avant la réplique, l'appel d'origine ne bouge pas.
        $done = $client->getRequest()->getPathInfo();
        $client->submit($client->getCrawler()->filter('form.replay')->first()->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.cs-pill', 'Reprise');
        $replay = $this->service(EntityManagerInterface::class)->getRepository(Training::class)->findOneBy([], ['id' => 'DESC']) ?? self::fail();
        self::assertNotNull($replay->replayOf);
        self::assertTrue($replay->isLive());
        self::assertSame([Training::PERSONA], array_column($replay->messages, 'role'));
        self::assertSame(0, \App\Game\Level::xp($replay));
        $client->request('GET', $done);
        self::assertSelectorTextContains('.transcript', 'Quel est votre projet ?');
        $client->request('GET', '/sessions/'.$replay->id);
        $client->submit($client->getCrawler()->filter('form[action$="/abandonner"]')->form());

        // Camille ne voit ni le tableau d'équipe ni les sessions des autres ; le manager voit tout.
        $client->request('GET', '/equipe');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/progression');
        self::assertSelectorTextContains('.kpis', '10');
        $client->loginUser($sellers[0]);
        $client->request('GET', '/equipe');
        self::assertSelectorTextContains('table', 'Camille Roux');
        $client->request('GET', '/sessions/'.$m[1]);
        self::assertResponseIsSuccessful();
        $em = $this->service(EntityManagerInterface::class);
        $client->loginUser($em->getRepository(Seller::class)->findOneBy(['email' => 'camille@etixio.test']) ?? self::fail());
        $other = $this->service(Trainer::class)->start($em->find(Seller::class, $sellers[0]->id) ?? self::fail(), $em->find(Scenario::class, $scenario->id) ?? self::fail());
        $client->request('GET', '/sessions/'.$other->id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheMcpServerKeepsTheBriefSealedUntilTheDebrief(): void
    {
        $client = $this->client();
        [$camille] = $this->sellers();
        $key = $camille->newMcpKey();
        $this->service(EntityManagerInterface::class)->flush();
        $scenario = $this->scenario();

        $client->request('POST', '/mcp?key=faux', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);

        $rpc = function (string $method, array $params = []) use ($client, $key): array {
            static $session = null;
            $client->request('POST', '/mcp', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => 'Bearer '.$key] + ($session ? ['HTTP_MCP_SESSION_ID' => $session] : []), content: (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]));
            $session ??= $client->getResponse()->headers->get('Mcp-Session-Id');

            return json_decode((string) $client->getResponse()->getContent(), true) ?? [];
        };
        $rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]);
        $tools = array_column($rpc('tools/list')['result']['tools'], 'name');
        foreach (['scenarios', 'start_sparring', 'reply', 'end_sparring', 'debrief', 'my_progress', 'create_scenario'] as $tool) {
            self::assertContains($tool, $tools);
        }

        $list = $rpc('tools/call', ['name' => 'scenarios', 'arguments' => (object) []]);
        self::assertSame($scenario->title, $list['result']['structuredContent']['scenarios'][0]['title']);
        $start = $rpc('tools/call', ['name' => 'start_sparring', 'arguments' => ['scenario' => $scenario->id]]);
        self::assertSame($scenario->opening, $start['result']['structuredContent']['persona_says']);
        $this->script = ['Six mois de retard.'];
        $reply = $rpc('tools/call', ['name' => 'reply', 'arguments' => ['message' => 'Quel est votre projet ?']]);
        self::assertSame('Six mois de retard.', $reply['result']['structuredContent']['persona_says']);
        self::assertSame('0 / 6', $reply['result']['structuredContent']['discovered']);
        self::assertStringNotContainsString('400 000', (string) json_encode([$list, $start, $reply], \JSON_UNESCAPED_UNICODE));

        $this->script = ['{"criteria": {"decouverte": {"score": 9}, "questions": {"score": 9}}, "summary": "Bien.", "tips": ["Un"]}'];
        $end = $rpc('tools/call', ['name' => 'end_sparring', 'arguments' => (object) []]);
        self::assertSame(30, $end['result']['structuredContent']['score']);
        $view = $rpc('tools/call', ['name' => 'debrief_view', 'arguments' => (object) []]);
        self::assertStringContainsString('400 000 €', $view['result']['structuredContent']['html']);
        $app = $rpc('resources/read', ['uri' => 'ui://sparring/debrief']);
        self::assertStringContainsString('debrief_view', $app['result']['contents'][0]['text']);
        self::assertSame(1, $rpc('tools/call', ['name' => 'my_progress', 'arguments' => (object) []])['result']['structuredContent']['done']);
    }

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->boot();

        return $client;
    }

    private function boot(): void
    {
        if (!static::$booted) {
            self::bootKernel();
        }
        $this->clock = new MockClock('2026-11-13 10:00:00');
        Clock::set($this->clock);
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
     * @return array{Seller, Seller, Seller}
     */
    private function sellers(): array
    {
        $em = $this->service(EntityManagerInterface::class);
        $repo = $em->getRepository(Seller::class);
        $out = [];
        foreach ([['Camille Roux', 'camille@etixio.test', Seller::SELLER], ['Sam Petit', 'sam@etixio.test', Seller::SELLER], ['Mathieu Bernard', 'boss@etixio.test', Seller::MANAGER]] as [$name, $email, $role]) {
            $out[] = $repo->findOneBy(['email' => $email]) ?? $this->member($name, $email, $role);
        }
        $em->flush();

        return [$out[0], $out[1], $out[2]];
    }

    private function scenario(?\App\Entity\Workspace $space = null): Scenario
    {
        $em = $this->service(EntityManagerInterface::class);
        $existing = $em->getRepository(Scenario::class)->findOneBy(['title' => 'Le DAF pressé qui veut un prix']);
        if (null !== $existing) {
            return $existing;
        }
        $library = Yaml::parseFile(\dirname(__DIR__).'/config/scenarios.yaml');
        $scenario = ScenarioWriter::fromArray($library[0]);
        $scenario->workspace = $space ?? $this->etixio();
        $em->persist($scenario);
        $em->flush();

        return $scenario;
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
