<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Call\CallException;
use App\Call\CallManager;
use App\Entity\Call;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\CallEnd;
use App\Enum\CallStatus;
use App\Enum\Speaker;
use App\Repository\CallRepository;
use App\Repository\CompanyRepository;
use App\Scenario\SurpriseCalls;
use App\Tests\Fake\ScriptedModel;
use App\Tests\Fake\SilentSynthesizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * A call's rules and its failures, from the website: what the seller may say and when, the persona's voice, the
 * model failing before or during a reply, the coach failing, replays and archived scenarios.
 */
final class CallRulesTest extends FunctionalTestCase
{
    private Member $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->me = $this->signIn($this->member());
    }

    public function testADrawRetriesOnceThenSaysTheModelFailed(): void
    {
        // The writer agent and its plain fallback both garbled: the draw is made again, and works.
        ScriptedModel::$garbled = 2;
        $this->draw();
        self::assertResponseIsSuccessful('A garbled answer is written again.');

        ScriptedModel::$unreachable = true;
        $this->draw();
        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('error', (string) $this->client->getResponse()->getContent());
    }

    public function testASituationWithoutObjectivesTakesTheCatalogues(): void
    {
        $answer = json_decode((string) json_encode(['title' => 'A call', 'context' => 'You call.', 'material' => 'The form.', 'goal' => 'Book.', 'objectives' => [], 'persona' => ['name' => 'Ana Diaz', 'gender' => 'f', 'role' => 'COO', 'company' => 'Freshcart', 'personality' => 'Busy.'], 'opening' => 'Ana speaking.', 'brief' => ['setup' => 'Busy.', 'budget' => '30k', 'pain' => 'Lost orders.', 'decision' => 'Her.', 'objections' => 'Price.', 'yes' => 'A workshop.', 'secret' => 'A rival quote.'], 'workspace' => ScriptedModel::workspace()]), true);
        ScriptedModel::$answers['You write phone call situations'] = (string) json_encode($answer);

        $scenario = $this->service(SurpriseCalls::class)->draw($this->me, 'price_request');

        self::assertSame($this->service(CompanyRepository::class)->get()->situations()['price_request']->objectives, $scenario->objectives());
    }

    public function testTheModelFailingBeforeOrDuringAReply(): void
    {
        $call = $this->live();

        ScriptedModel::$unreachable = true;
        $this->talk($call, 'Hello, Ana.');
        self::assertResponseStatusCodeSame(502);

        ScriptedModel::$unreachable = false;
        ScriptedModel::$cutOff = true;
        $this->talk($call, 'Hello again.');
        $events = $this->events();
        self::assertSame('error', end($events)['t']);
        self::assertNotSame('call.error.line_cut', end($events)['error'], 'The error is translated.');
    }

    public function testTheSellerSaysTooMuchOrTooLate(): void
    {
        $call = $this->live();
        $this->talk($call, str_repeat('word ', 401));
        self::assertResponseStatusCodeSame(400);

        $late = $this->live(new \DateTimeImmutable('-2 hours'));
        $this->talk($late, 'Are you still there?');
        self::assertResponseStatusCodeSame(400);
        self::assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['ended']);
        self::assertSame(CallEnd::Clock, $this->reload($late)->endedBy);
    }

    public function testTheCallStopsAtTheLastTurn(): void
    {
        $call = $this->live();
        $now = $this->service(ClockInterface::class)->now();
        for ($i = 1; $i < Call::MAX_TURNS; ++$i) {
            $call->say(Speaker::Seller, 'Line '.$i, $now);
            $call->say(Speaker::Persona, 'I see.', $now);
        }
        $this->service(EntityManagerInterface::class)->flush();

        $this->talk($call, 'One more question.');
        $events = $this->events();
        self::assertTrue(end($events)['ended']);
        self::assertSame(CallEnd::Clock, $this->reload($call)->endedBy);
    }

    public function testThePersonaSpeaksWithAVoice(): void
    {
        SilentSynthesizer::$voiced = true;
        $call = $this->live();

        $this->talk($call, 'Hello, Ana.');
        $said = array_values(array_filter($this->events(), static fn (array $e): bool => 'say' === $e['t']));
        self::assertNotEmpty($said);
        self::assertStringStartsWith('MP3:', (string) base64_decode((string) $said[0]['audio'], true));

        $this->client->request('GET', '/calls/'.$call->id.'/voice/0');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'audio/mpeg');

        $this->client->request('GET', '/calls/'.$call->id.'/voice/1');
        self::assertResponseStatusCodeSame(404, 'Line 1 is the seller\'s.');
        $this->client->request('GET', '/calls/'.$call->id.'/voice/99');
        self::assertResponseStatusCodeSame(404);

        SilentSynthesizer::$failing = true;
        $this->client->request('GET', '/calls/'.$call->id.'/voice/0');
        self::assertResponseStatusCodeSame(503);
    }

    public function testThePersonaMayAnswerInAWord(): void
    {
        ScriptedModel::$answers['You are playing a part in a sales training'] = 'Yes?';
        $call = $this->live();

        $this->talk($call, 'Ana?');
        $said = array_values(array_filter($this->events(), static fn (array $e): bool => 'say' === $e['t']));
        self::assertSame('Yes?', $said[0]['text'] ?? null);
    }

    public function testOnlyALiveCallCanBeAbandoned(): void
    {
        $call = $this->live();
        $manager = $this->service(CallManager::class);
        $manager->abandon($this->reload($call));

        $this->expectExceptionObject(new CallException('call.error.not_live'));
        $manager->abandon($this->reload($call));
    }

    public function testTheCoachFailingKeepsTheCallForLater(): void
    {
        $call = $this->live();
        $this->talk($call, 'What budget have you set aside?');
        ScriptedModel::$unreachable = true;

        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $this->client->submit($crawler->filter('form[action$="/finish"]')->form());
        self::assertResponseRedirects('/calls/'.$call->id);
        self::assertSame(CallStatus::Debriefing, $this->reload($call)->status);
    }

    public function testReplaysNeedAFinishedCallAndASellerLine(): void
    {
        $call = $this->live();
        $this->talk($call, 'What budget have you set aside?');
        try {
            $this->service(CallManager::class)->replay($this->reload($call), 1);
            self::fail('A live call cannot be replayed.');
        } catch (CallException $e) {
            self::assertSame('call.error.finish_first', $e->getMessage());
        }

        $this->client->request('POST', '/calls/'.$call->id.'/abandon', ['_token' => $this->callToken($call)]);
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $token = (string) $crawler->filter('form[action*="/replay/"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/calls/'.$call->id.'/replay/0', ['_token' => $token]);
        self::assertResponseRedirects('/calls/'.$call->id, message: 'Line 0 is the persona\'s.');
        self::assertCount(1, $this->service(CallRepository::class)->all());
    }

    public function testAnArchivedScenarioCannotBeCalledAndANewCallEndsTheLiveOne(): void
    {
        $first = $this->live();
        $scenario = $this->service(SurpriseCalls::class)->draw($this->service(EntityManagerInterface::class)->find(Member::class, $this->me->id));

        $crawler = $this->client->request('GET', '/call/'.$scenario->id);
        $form = $crawler->filter('form[action^="/calls/start/"]')->form();
        $this->client->submit($form);
        self::assertSame(CallStatus::Abandoned, $this->reload($first)->status, 'Picking up another call drops the live one.');

        $scenario = $this->service(EntityManagerInterface::class)->find(Scenario::class, $scenario->id);
        $scenario->active = false;
        $this->service(EntityManagerInterface::class)->flush();
        $this->client->submit($form);
        self::assertResponseRedirects('/call/'.$scenario->id);
    }

    private function draw(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->request('POST', '/call/new', ['token' => $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value')], server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * A live call of the signed-in seller, started at the given time (now by default).
     */
    private function live(?\DateTimeImmutable $startedAt = null): Call
    {
        // Each request boots the kernel again: the member is read from the current entity manager.
        $em = $this->service(EntityManagerInterface::class);
        $me = $em->find(Member::class, $this->me->id);
        $scenario = $this->service(SurpriseCalls::class)->draw($me, 'price_request');
        $call = new Call($me, $scenario, $startedAt ?? $this->service(ClockInterface::class)->now());
        $em->persist($call);
        $em->flush();

        return $call;
    }

    private function talk(Call $call, string $message): void
    {
        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $this->callToken($call)], content: (string) json_encode(['message' => $message]));
    }

    private function callToken(Call $call): string
    {
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $node = $crawler->filter('[data-call-token-value]');

        return $node->count() > 0 ? (string) $node->attr('data-call-token-value') : (string) $crawler->filter('form[action$="/abandon"] input[name=_token], form[action$="/finish"] input[name=_token]')->attr('value');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function events(): array
    {
        $lines = array_values(array_filter(explode("\n", (string) $this->client->getInternalResponse()->getContent())));

        return array_map(static fn (string $line): array => json_decode($line, true, flags: \JSON_THROW_ON_ERROR), $lines);
    }

    private function reload(Call $call): Call
    {
        $em = $this->service(EntityManagerInterface::class);
        $em->clear();

        return $em->find(Call::class, $call->id);
    }
}
