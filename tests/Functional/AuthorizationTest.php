<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Call;
use App\Entity\Member;
use App\Scenario\ScenarioWriter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What one member must never reach of another's: their calls (to play, hang up, abandon or replay), their private
 * rehearsals, and, for sellers, the library's editing. And the client's hidden brief, before the call is over.
 */
final class AuthorizationTest extends FunctionalTestCase
{
    private Member $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->owner = $this->member('owner@decroche.test');
    }

    public function testAnotherSellerCannotActOnSomeoneElsesCall(): void
    {
        $call = $this->liveCall($this->owner);
        $this->signIn($this->member('other@decroche.test'));
        $this->client->request('GET', '/');
        // A token valid in the other seller's own session: only the permission can refuse them.
        $token = $this->csrfToken('call-'.$call->id);

        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: (string) json_encode(['message' => 'Hello?']));
        self::assertResponseStatusCodeSame(403);
        foreach (['finish', 'abandon', 'replay/1'] as $action) {
            $this->client->request('POST', '/calls/'.$call->id.'/'.$action, ['_token' => $token]);
            self::assertResponseStatusCodeSame(403, $action);
        }
        $this->client->request('GET', '/calls/'.$call->id.'/voice/0');
        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->fresh($call)->isLive(), 'The owner\'s call goes on untouched.');
    }

    public function testAPrivateRehearsalStaysWithItsAuthor(): void
    {
        $rehearsal = $this->service(ScenarioWriter::class)->prepare('Freshcart, a grocery delivery start-up: Ana, their CTO, asked for our rates.', $this->owner);
        $em = $this->service(EntityManagerInterface::class);
        $em->persist($rehearsal);
        $em->flush();

        $this->signIn($this->member('other@decroche.test'));
        foreach (['/call/'.$rehearsal->id, '/library/'.$rehearsal->id] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
        $this->client->request('GET', '/');
        $this->client->request('POST', '/calls/start/'.$rehearsal->id, ['_token' => $this->csrfToken('call-start-'.$rehearsal->id)]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSellersCannotEditTheLibrary(): void
    {
        $this->signIn($this->owner);
        $this->client->request('GET', '/library/1/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/library/1/archive', ['_token' => $this->csrfToken('archive-1')]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheHiddenBriefNeverShowsDuringTheCall(): void
    {
        $call = $this->liveCall($this->owner);
        $this->signIn($this->owner);

        $this->client->request('GET', '/call/'.$call->scenario->id);
        $briefing = (string) $this->client->getResponse()->getContent();
        $token = $this->callToken($call);
        $phone = (string) $this->client->getResponse()->getContent();
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: (string) json_encode(['message' => 'What budget have you set aside?']));
        $stream = (string) $this->client->getInternalResponse()->getContent();

        foreach (['A competitor already quoted.', 'Orders are lost between tools.', 'She decides with her CFO.'] as $secret) {
            self::assertStringNotContainsString($secret, $briefing.$phone.$stream, $secret);
        }
        // The persona's tags are for the server only.
        self::assertStringContainsString('thirty thousand euros', $stream);
        self::assertStringNotContainsString('[devoiler', $stream);
    }

    private function fresh(Call $call): Call
    {
        $fresh = $this->service(EntityManagerInterface::class)->find(Call::class, $call->id);
        self::assertInstanceOf(Call::class, $fresh);

        return $fresh;
    }
}
