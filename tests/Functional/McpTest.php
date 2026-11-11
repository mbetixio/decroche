<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Company\DemoCompany;
use App\Company\TeamManager;
use App\Entity\Member;
use App\Enum\Language;
use App\Tests\Fake\ScriptedModel;

/**
 * The MCP server, as an AI client uses it: authenticated by the member's personal key, it draws a call and hands over a
 * signed link to take it out loud, plays a call in the chat, gives the figures, and lets managers read their team.
 */
final class McpTest extends FunctionalTestCase
{
    private string $key;
    private ?string $session = null;
    private int $id = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->onboardedCompany();
    }

    public function testAnUnknownKeyIsRefused(): void
    {
        $this->client->request('POST', '/mcp?key=pcl_forged', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], content: '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheToolsAreListed(): void
    {
        $this->connect($this->member());
        $tools = array_column($this->rpc('tools/list')['tools'], 'name');
        foreach (['situations', 'new_call', 'prepare_call', 'call_link', 'reply', 'hang_up', 'my_progress', 'team', 'give_mission', 'debrief'] as $tool) {
            self::assertContains($tool, $tools);
        }
    }

    public function testANewCallComesWithASignedLinkThatSignsTheSellerIn(): void
    {
        $this->connect($this->member());
        $result = $this->tool('new_call', ['situation' => 'price_request']);
        self::assertSame('A price request from the website', $result['scenario']['title']);
        self::assertArrayNotHasKey('brief', $result['scenario'], 'The hidden brief never leaves the server.');

        // The link signs the seller in (no cookie yet) and opens the briefing.
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', $result['link']);
        self::assertResponseRedirects('/call/'.$result['scenario']['id']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'A price request from the website');
    }

    public function testTheReadyMadeRequestsChainTheTools(): void
    {
        $this->connect($this->member());
        self::assertSame(['rehearse_meeting', 'review_my_calls', 'coach_my_team'], array_column($this->rpc('prompts/list')['prompts'], 'name'));

        $prompt = $this->rpc('prompts/get', ['name' => 'rehearse_meeting', 'arguments' => (object) ['meeting' => 'Claire at Alpine, Thursday']]);
        $text = $prompt['messages'][0]['content']['text'];
        self::assertStringContainsString('Claire at Alpine, Thursday', $text);
        self::assertStringContainsString('my e-mails, my calendar', $text);
        self::assertStringContainsString('prepare_call', $text);
    }

    public function testTheOfferSituationsAndScorecardAreResources(): void
    {
        $this->connect($this->member());
        $uris = array_column($this->rpc('resources/list')['resources'], 'uri');
        foreach (['decroche://offer', 'decroche://situations', 'decroche://scorecard'] as $uri) {
            self::assertContains($uri, $uris);
        }

        $offer = $this->rpc('resources/read', ['uri' => 'decroche://offer'])['contents'][0];
        self::assertStringStartsWith("Etixio\n\n", $offer['text']);
        self::assertStringContainsString('The classic objections to offshore', $offer['text']);
        $scorecard = json_decode($this->rpc('resources/read', ['uri' => 'decroche://scorecard'])['contents'][0]['text'], true);
        self::assertSame('closing', end($scorecard['criteria'])['key']);
    }

    public function testANewCallCanTrainOneCriterion(): void
    {
        $this->connect($this->member());
        ScriptedModel::$prompts = [];
        $this->tool('new_call', ['focus' => 'objections']);

        self::assertStringContainsString('The seller wants to train one skill, graded low in their last debrief: Handling objections', ScriptedModel::$prompts[0]);
    }

    public function testALibraryScenarioIsPlayedInTheChat(): void
    {
        $this->service(DemoCompany::class)->loadLibrary(Language::English);
        $this->connect($this->member());
        $scenarios = $this->tool('library')['scenarios'];
        self::assertNotEmpty($scenarios);
        self::assertArrayNotHasKey('brief', $scenarios[0], 'The hidden brief never leaves the server.');

        $started = $this->tool('start_call', ['scenario' => $scenarios[0]['id']]);
        self::assertSame($scenarios[0]['title'], $started['scenario']['title']);
        self::assertNotSame('', $started['persona_says']);
        self::assertIsInt($started['call']);
    }

    public function testAMeetingFromAnEmailIsRehearsedThroughMcp(): void
    {
        $this->connect($this->member());
        $situations = $this->tool('situations');
        self::assertSame('Etixio', $situations['company']);
        self::assertContains('price_request', array_column($situations['situations'], 'key'));

        $result = $this->tool('prepare_call', ['prospect' => "From: Ana Diaz <ana@freshcart.example>\nWe lose orders between our tools and would like your rates before Thursday's call."]);
        self::assertStringContainsString('/login/link', $result['link']);
        self::assertArrayNotHasKey('brief', $result['scenario'], 'The hidden brief never leaves the server.');

        $link = $this->tool('call_link', ['scenario' => $result['scenario']['id']]);
        self::assertSame(15, $link['valid_minutes']);
    }

    public function testMcpErrorsAreToldPlainly(): void
    {
        $this->connect($this->member());

        $tooShort = $this->rpc('tools/call', ['name' => 'prepare_call', 'arguments' => (object) ['prospect' => 'Ana']]);
        self::assertTrue($tooShort['isError']);
        $unknown = $this->rpc('tools/call', ['name' => 'call_link', 'arguments' => (object) ['scenario' => 999]]);
        self::assertTrue($unknown['isError']);
        self::assertStringContainsString('No scenario #999', $unknown['content'][0]['text']);
    }

    public function testACallPlayedInTheChat(): void
    {
        $this->connect($this->member());
        $started = $this->tool('new_call', ['mode' => 'chat']);
        self::assertSame('Ana Diaz speaking, hello?', $started['persona_says']);

        $turn = $this->tool('reply', ['message' => 'What budget have you set aside?']);
        self::assertStringContainsString('thirty thousand euros', $turn['persona_says']);
        self::assertSame('1 / 6', $turn['uncovered']);

        $debrief = $this->tool('hang_up');
        self::assertSame('Budget found, no next step', $debrief['result']);
        self::assertNotNull($debrief['score']);

        $app = $this->tool('debrief');
        self::assertStringContainsString('Orders are lost', $app['html']);
        self::assertArrayNotHasKey('member', $app);
    }

    public function testSellersReadTheirFiguresButNotTheTeam(): void
    {
        $this->connect($this->member());
        self::assertSame('Seller Test', $this->tool('my_progress')['member']);
        $refused = $this->rpc('tools/call', ['name' => 'team', 'arguments' => []]);
        self::assertTrue($refused['isError']);
        self::assertStringContainsString('managers only', $refused['content'][0]['text']);
    }

    public function testAManagerReadsTheTeamAndGivesAMission(): void
    {
        $this->member();
        $this->connect($this->manager());
        $team = $this->tool('team');
        self::assertCount(2, $team['members']);

        $mission = $this->tool('give_mission', ['email' => 'seller@decroche.test', 'situation' => 'price_request', 'calls' => 2, 'target' => 70]);
        self::assertSame('Seller Test', $mission['for']);
        self::assertSame(1, \count($this->tool('member_progress', ['email' => 'seller@decroche.test'])['missions']));

        $invitation = $this->tool('invite', ['email' => 'new@decroche.test']);
        self::assertStringContainsString('/join/', $invitation['link']);
    }

    private function connect(Member $member): void
    {
        $this->key = $this->service(TeamManager::class)->newMcpKey($member);
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]);
        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
    }

    /**
     * Calls a tool and returns its structured result.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function tool(string $name, array $arguments = []): array
    {
        $result = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
        self::assertFalse($result['isError'] ?? false, $name.': '.($result['content'][0]['text'] ?? ''));

        return $result['structuredContent'] ?? json_decode($result['content'][0]['text'], true);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        $response = $this->post(['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => $method, 'params' => (object) $params]);
        self::assertArrayHasKey('result', $response, (string) json_encode($response));

        return $response['result'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        $this->client->request('POST', '/mcp?key='.$this->key, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'] + (null !== $this->session ? ['HTTP_MCP_SESSION_ID' => $this->session] : []), content: (string) json_encode($payload));
        $this->session ??= $this->client->getResponse()->headers->get('Mcp-Session-Id');
        $body = (string) $this->client->getInternalResponse()->getContent();

        return '' === trim($body) ? [] : json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
    }
}
