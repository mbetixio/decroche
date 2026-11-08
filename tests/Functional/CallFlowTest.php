<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Call;
use App\Enum\BriefSection;
use App\Enum\CallStatus;
use App\Repository\CallRepository;

/**
 * A whole call from the website: draw it, read the briefing, pick up, talk (a streamed reply), hang up, read the
 * debrief, replay a moment.
 */
final class CallFlowTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->signIn($this->member());
    }

    public function testASurpriseCallIsDrawnThenBriefed(): void
    {
        $crawler = $this->client->request('GET', '/');
        self::assertSelectorTextContains('h1', 'Ready for a call?');
        $token = $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value');

        $this->client->request('POST', '/call/new', ['token' => $token, 'situation' => 'price_request'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $url = json_decode((string) $this->client->getResponse()->getContent(), true)['url'];

        $this->client->request('GET', $url);
        self::assertSelectorTextContains('h1', 'A price request from the website');
        self::assertSelectorTextContains('.doc', 'we need a quote');
        self::assertSelectorTextNotContains('main', 'Orders are lost', 'The hidden brief never shows before the debrief.');
    }

    public function testDrawingACallNeedsAValidToken(): void
    {
        $this->client->request('POST', '/call/new', ['token' => 'forged']);
        self::assertResponseRedirects('http://localhost/login');
        self::assertSame([], $this->service(CallRepository::class)->all());
    }

    public function testACallFromPickUpToDebrief(): void
    {
        $call = $this->pickUp();
        self::assertSelectorExists('[data-controller=call]');
        $token = $this->client->getCrawler()->filter('[data-controller=call]')->attr('data-call-token-value');

        // The seller talks; the persona answers as a stream of JSON lines, revealing their budget.
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/x-ndjson', 'HTTP_X_CSRF_TOKEN' => $token], content: json_encode(['message' => 'What budget have you set aside?', 'voiceMs' => 2400]));
        self::assertResponseIsSuccessful();
        $events = $this->events();
        self::assertSame('say', $events[0]['t']);
        self::assertStringContainsString('thirty thousand euros', implode(' ', array_column(array_filter($events, static fn (array $e): bool => 'say' === $e['t']), 'text')));
        self::assertStringNotContainsString('[devoiler', (string) $this->client->getInternalResponse()->getContent());
        $done = end($events);
        self::assertSame('done', $done['t']);
        self::assertSame(1, $done['revealed']);
        self::assertFalse($done['ended']);

        // Hang up: the coach writes the debrief.
        $this->hangUp($call);
        self::assertResponseRedirects('/calls/'.$call->id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Budget found, no next step');
        self::assertSelectorTextContains('main', 'Orders are lost', 'After the debrief, the hidden brief is revealed.');

        $call = $this->call($call->id);
        self::assertSame(CallStatus::Done, $call->status);
        self::assertNotNull($call->score);
        self::assertSelectorTextContains('.verdict-score', (string) $call->score);
        self::assertTrue($call->isRevealed(BriefSection::Budget));
        self::assertTrue($call->isSpoken());
    }

    public function testThePersonaCanHangUp(): void
    {
        $call = $this->pickUp();
        $token = $this->client->getCrawler()->filter('[data-controller=call]')->attr('data-call-token-value');
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: json_encode(['message' => 'Alright, goodbye.']));
        $events = $this->events();
        $done = end($events);
        self::assertTrue($done['ended']);
        self::assertSame('persona', $done['endedBy']);
        self::assertSame(CallStatus::Debriefing, $this->call($call->id)->status);
    }

    public function testTalkingNeedsTheCallToken(): void
    {
        $call = $this->pickUp();
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'forged'], content: json_encode(['message' => 'Hello']));
        self::assertResponseRedirects('http://localhost/login');
        self::assertCount(1, $this->call($call->id)->lines(), 'Nothing was said.');
    }

    public function testAnEmptyLineIsRefused(): void
    {
        $call = $this->pickUp();
        $token = $this->client->getCrawler()->filter('[data-controller=call]')->attr('data-call-token-value');
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: json_encode(['message' => '   ']));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('Say something.', json_decode((string) $this->client->getResponse()->getContent(), true)['error']);
    }

    public function testAnAbandonedCallIsNotGradedButRevealsTheBrief(): void
    {
        $call = $this->pickUp();
        $this->submit('Abandon without a debrief');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'No score for an abandoned call');
        self::assertSelectorTextContains('main', 'Orders are lost');
        self::assertNull($this->call($call->id)->score);
    }

    public function testAMomentCanBeReplayed(): void
    {
        $call = $this->pickUp();
        $token = $this->client->getCrawler()->filter('[data-controller=call]')->attr('data-call-token-value');
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: json_encode(['message' => 'Hello, it is about your request.']));
        $this->hangUp($call);
        $this->client->followRedirect();

        $this->submit('Replay from here');
        self::assertResponseRedirects();
        $replay = $this->service(CallRepository::class)->current($this->call($call->id)->member);
        self::assertNotNull($replay);
        self::assertTrue($replay->isReplay());
        self::assertCount(1, $replay->lines(), 'The replay resumes just before the seller\'s line.');
    }

    public function testAnotherSellerCannotSeeTheCall(): void
    {
        $call = $this->pickUp();
        $this->signIn($this->member('other@practicall.test'));
        $this->client->request('GET', '/calls/'.$call->id);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAManagerCanReadASellersDebrief(): void
    {
        $call = $this->pickUp();
        $this->hangUp($call);
        $this->signIn($this->manager());
        $this->client->request('GET', '/calls/'.$call->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.eyebrow', 'Seller Test');
        self::assertSelectorNotExists('form[action$="/replay/1"]');
    }

    /**
     * Draws a surprise call (written by the scripted model), picks up and lands on the phone screen.
     */
    private function pickUp(): Call
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->request('POST', '/call/new', ['token' => $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value')]);
        $this->client->request('GET', json_decode((string) $this->client->getResponse()->getContent(), true)['url']);
        $this->client->submit($this->client->getCrawler()->filter('form[action^="/calls/start/"]')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        return $this->service(CallRepository::class)->all()[0];
    }

    private function hangUp(Call $call): void
    {
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $this->client->submit($crawler->filter('form[action$="/finish"]')->form());
    }

    /**
     * The events of the last streamed reply.
     *
     * @return list<array<string, mixed>>
     */
    private function events(): array
    {
        $lines = array_values(array_filter(explode("\n", (string) $this->client->getInternalResponse()->getContent())));

        return array_map(static fn (string $line): array => json_decode($line, true, flags: \JSON_THROW_ON_ERROR), $lines);
    }

    private function call(int $id): Call
    {
        $em = $this->service('doctrine.orm.entity_manager');
        $em->clear();

        return $em->find(Call::class, $id);
    }
}
