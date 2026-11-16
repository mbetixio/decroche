<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Invitation;
use App\Entity\Mission;
use App\Entity\Scenario;
use App\Enum\Difficulty;
use App\Team\TeamManager;
use App\Tests\Fake\ScriptedModel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The forms Claude shows the person before a tool acts (MCP elicitation), over the stateless revision of the protocol:
 * the tool answers with the form, already filled in by the AI; the client sends the call again with the person's
 * answers, and only then is anything done.
 */
final class McpFormsTest extends FunctionalTestCase
{
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->onboardedCompany();
    }

    public function testAnInvitationIsConfirmedAndCorrectedByTheManager(): void
    {
        $this->key = $this->service(TeamManager::class)->newMcpKey($this->manager());

        $form = $this->call('invite', ['email' => 'new@decroche.test']);
        self::assertSame('input_required', $form['resultType']);
        $ask = $form['inputRequests']['elicitation_1']['params'];
        self::assertSame('new@decroche.test', $ask['requestedSchema']['properties']['email']['default'], 'The AI fills the form in.');
        self::assertSame('seller', $ask['requestedSchema']['properties']['role']['default']);
        self::assertSame(0, $this->rows(Invitation::class), 'Nothing is done before the answer.');

        $done = $this->call('invite', ['email' => 'new@decroche.test'], ['action' => 'accept', 'content' => ['email' => 'lead@decroche.test', 'role' => 'manager']]);
        self::assertSame('lead@decroche.test', $done['structuredContent']['email']);
        self::assertSame('manager', $done['structuredContent']['role']);
        self::assertStringContainsString('/join/', $done['structuredContent']['link']);
    }

    public function testACancelledFormDoesNothing(): void
    {
        $this->key = $this->service(TeamManager::class)->newMcpKey($this->manager());
        $this->member();

        foreach (['invite' => ['email' => 'new@decroche.test'], 'give_mission' => ['email' => 'seller@decroche.test']] as $tool => $arguments) {
            foreach (['cancel', 'decline'] as $action) {
                $refused = $this->call($tool, $arguments, ['action' => $action]);
                self::assertTrue($refused['isError'], $tool);
                self::assertStringContainsString('Cancelled by the user', $refused['content'][0]['text']);
            }
        }
        self::assertSame(0, $this->rows(Invitation::class));
        self::assertSame(0, $this->rows(Mission::class));
    }

    public function testAMissionIsAdjustedByTheManager(): void
    {
        $this->key = $this->service(TeamManager::class)->newMcpKey($this->manager());
        $this->member();

        $form = $this->call('give_mission', ['email' => 'seller@decroche.test', 'situation' => 'price_request', 'calls' => 2]);
        $ask = $form['inputRequests']['elicitation_1']['params'];
        self::assertStringContainsString('Give Seller Test this mission? Situation: Pricing request on the website.', $ask['message']);
        self::assertSame(2, $ask['requestedSchema']['properties']['calls']['default']);

        $mission = $this->call('give_mission', ['email' => 'seller@decroche.test', 'situation' => 'price_request', 'calls' => 2], ['action' => 'accept', 'content' => ['calls' => 5, 'target' => 80, 'due' => '', 'note' => 'Before the trade fair.']]);
        self::assertSame(5, $mission['structuredContent']['calls']);
        self::assertSame(80, $mission['structuredContent']['target_score']);
        self::assertSame('Before the trade fair.', $this->service(EntityManagerInterface::class)->getRepository(Mission::class)->findAll()[0]->note);
    }

    public function testTheSellerSaysWhatTheyWantFromTheMeeting(): void
    {
        $this->key = $this->service(TeamManager::class)->newMcpKey($this->member());
        $meeting = ['prospect' => "From: Ana Diaz <ana@freshcart.example>\nWe lose orders between our tools and would like your rates before Thursday's call.", 'goal' => 'Send a quote'];

        $ask = $this->call('prepare_call', $meeting)['inputRequests']['elicitation_1']['params'];
        self::assertSame('Send a quote', $ask['requestedSchema']['properties']['goal']['default']);
        self::assertSame(['1', '2', '3'], array_column($ask['requestedSchema']['properties']['difficulty']['oneOf'], 'const'));

        ScriptedModel::$prompts = [];
        $result = $this->call('prepare_call', $meeting, ['action' => 'accept', 'content' => ['goal' => 'Book a demo with their CFO', 'difficulty' => '3', 'minutes' => 6]]);
        self::assertStringContainsString("The seller's own goal for this meeting: Book a demo with their CFO", implode("\n", ScriptedModel::$prompts));
        $scenario = $this->service(EntityManagerInterface::class)->find(Scenario::class, $result['structuredContent']['scenario']['id']);
        self::assertSame(Difficulty::Tough, $scenario->difficulty);
        self::assertSame(6, $scenario->minutes);
    }

    /**
     * A tools/call over the stateless revision, from a client that can show forms; with an answer, the call is sent
     * again with it, as a client does once the person has filled the form in.
     *
     * @param array<string, mixed>      $arguments
     * @param array<string, mixed>|null $answer
     *
     * @return array<string, mixed>
     */
    private function call(string $tool, array $arguments, ?array $answer = null): array
    {
        $params = ['name' => $tool, 'arguments' => (object) $arguments, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => ['elicitation' => ['form' => new \stdClass()]],
            'io.modelcontextprotocol/clientInfo' => ['name' => 'test', 'version' => '1'],
        ]];
        if (null !== $answer) {
            $params['inputResponses'] = ['elicitation_1' => $answer];
        }
        $this->client->request('POST', '/mcp?key='.$this->key, server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28', 'HTTP_MCP_METHOD' => 'tools/call', 'HTTP_MCP_NAME' => $tool,
        ], content: (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => $params]));
        $response = json_decode((string) $this->client->getInternalResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('result', $response, (string) json_encode($response));

        return $response['result'];
    }

    /**
     * @param class-string $entity
     */
    private function rows(string $entity): int
    {
        return \count($this->service(EntityManagerInterface::class)->getRepository($entity)->findAll());
    }
}
