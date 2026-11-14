<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Demo\DemoCalls;
use App\Enum\CallStatus;
use App\Enum\Language;
use App\Repository\CallRepository;
use App\Repository\MemberRepository;
use Psr\Clock\ClockInterface;

/**
 * The demo's team and debriefed calls: loaded in both languages, dated back from today, shown as any other call.
 */
final class DemoCallsTest extends FunctionalTestCase
{
    public function testTheDemoTeamArrivesWithItsDebriefedCalls(): void
    {
        $this->onboardedCompany();
        $manager = $this->manager();

        self::assertSame(6, $this->service(DemoCalls::class)->load(Language::English, $manager));

        self::assertSame('Alex Morgan', $manager->name, 'The signed-in manager plays the example lead.');
        $junior = $this->service(MemberRepository::class)->findOneByEmail('jordan.lee@decroche.test');
        self::assertNotNull($junior);
        $calls = $this->service(CallRepository::class)->all();
        self::assertCount(6, $calls);
        // The calls were played with the agents: the clients looked their files up, and booked meetings in the best ones.
        self::assertNotEmpty(array_merge(...array_map(static fn ($c): array => $c->actions(), $calls)));
        self::assertNotEmpty(array_merge(...array_map(static fn ($c): array => $c->bookings(), $calls)));
        foreach ($calls as $call) {
            self::assertSame(CallStatus::Done, $call->status);
            self::assertNotNull($call->score);
            self::assertLessThan($this->service(ClockInterface::class)->now(), $call->startedAt);
        }

        $this->signIn($manager);
        $this->client->request('GET', '/team');
        self::assertSelectorTextContains('main', 'Jordan Lee');
        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('a[href^="/calls/"]')->first()->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.verdict-score');
    }

    public function testTheFrenchDemoQuotesTheSellerWordForWord(): void
    {
        $this->onboardedCompany(Language::French);
        $this->service(DemoCalls::class)->load(Language::French, $this->manager());

        foreach ($this->service(CallRepository::class)->all() as $call) {
            foreach ([...$call->debrief['best'], ...$call->debrief['worst']] as $quote) {
                self::assertNotNull($call->lineOf($quote['quote']), \sprintf('"%s" is a line of the seller.', $quote['quote']));
            }
        }
    }
}
