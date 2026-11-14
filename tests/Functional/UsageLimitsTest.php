<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Call;
use App\Entity\Member;
use App\Enum\CallStatus;
use App\Security\Usage;
use App\Security\UsageLimits;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Each member's share of the model: once it is spent, the website says so plainly, whatever was asked (a call written,
 * a line, a debrief). The kernel is kept between requests, so the quota spent here is the one the requests see.
 */
final class UsageLimitsTest extends FunctionalTestCase
{
    private Member $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->disableReboot();
        $this->onboardedCompany();
        $this->me = $this->signIn($this->member());
    }

    public function testEveryKindOfUseHasItsOwnQuota(): void
    {
        $limits = $this->service(UsageLimits::class);
        $this->spend(Usage::Writing);

        try {
            $limits->consume($this->me, Usage::Writing);
            self::fail('A spent quota refuses.');
        } catch (\DomainException $e) {
            self::assertSame('call.error.limit', $e->getMessage());
        }
        // The other quotas, and the other members, are untouched.
        $limits->consume($this->me, Usage::Talking);
        $limits->consume($this->me, Usage::Coaching);
        $limits->consume($this->member('other@decroche.test'), Usage::Writing);
    }

    public function testASpentWritingQuotaIsSaidWhenDrawing(): void
    {
        $crawler = $this->client->request('GET', '/');
        $token = $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value');
        $this->spend(Usage::Writing);

        $this->client->request('POST', '/call/new', ['token' => $token], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('the model needs a break', (string) $this->client->getResponse()->getContent());

        // Followed step by step, the waiting screen tells the same thing instead of a failure.
        $this->client->request('POST', '/call/new', ['token' => $token], server: ['HTTP_ACCEPT' => 'application/x-ndjson']);
        $end = $this->lines()[0];
        self::assertSame('error', $end['t']);
        self::assertStringContainsString('the model needs a break', $end['error']);
    }

    public function testASpentWritingQuotaIsSaidWhenRehearsing(): void
    {
        $crawler = $this->client->request('GET', '/prepare');
        $this->spend(Usage::Writing);
        $this->client->submit($crawler->selectButton('Write the rehearsal')->form(), ['prepare[text]' => 'Freshcart, a grocery delivery start-up: Ana, their CTO, asked for our rates.']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'the model needs a break');
    }

    public function testASpentTalkingQuotaIsSaidDuringTheCall(): void
    {
        $call = $this->liveCall($this->me);
        $token = $this->callToken($call);
        $this->spend(Usage::Talking);

        $this->client->request('POST', '/calls/'.$call->id.'/talk', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token], content: (string) json_encode(['message' => 'Hello?']));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('the model needs a break', (string) $this->client->getResponse()->getContent());
    }

    public function testASpentCoachingQuotaKeepsTheCallHungUpWithoutADebrief(): void
    {
        $call = $this->liveCall($this->me);
        $token = $this->callToken($call);
        $this->spend(Usage::Coaching);

        $this->client->request('POST', '/calls/'.$call->id.'/finish', ['_token' => $token]);
        self::assertResponseRedirects('/calls/'.$call->id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'the model needs a break');
        self::assertSame(CallStatus::Debriefing, $this->call($call)->status);
    }

    public function testAnAbandonedCallIsNeverGraded(): void
    {
        $call = $this->liveCall($this->me);
        $token = $this->callToken($call);
        $this->client->request('POST', '/calls/'.$call->id.'/abandon', ['_token' => $token]);

        $this->client->request('POST', '/calls/'.$call->id.'/finish', ['_token' => $token]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'it has no graded debrief');
        self::assertNull($this->call($call)->score);
    }

    private function spend(Usage $usage): void
    {
        /** @var RateLimiterFactoryInterface $factory */
        $factory = $this->service('limiter.ai_'.$usage->value);
        $factory->create('member-'.$this->me->id)->consume($this->limit($usage));
    }

    private function limit(Usage $usage): int
    {
        // The quotas set in .env.
        return (int) $_SERVER[Usage::Talking === $usage ? 'AI_LIMIT_LINES_PER_MINUTE' : 'AI_LIMIT_CALLS_PER_HOUR'];
    }

    private function call(Call $call): Call
    {
        $fresh = $this->service(\Doctrine\ORM\EntityManagerInterface::class)->find(Call::class, $call->id);
        self::assertInstanceOf(Call::class, $fresh);

        return $fresh;
    }
}
