<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Agent\AgentContext;
use App\Agent\Coach\ClientActions;
use App\Agent\Persona\BookMeeting;
use App\Agent\Persona\CheckCalendar;
use App\Agent\Persona\LookUp;
use App\Agent\ReadOffer;
use App\Call\CallManager;
use App\Entity\Call;
use App\Entity\Member;

/**
 * The agents' tools at work: the persona checks their calendar, books a meeting, looks a ticket up and remembers an
 * earlier call; the coach then reads what they did. And the tools' answers to what a model may get wrong.
 */
final class AgentToolsTest extends FunctionalTestCase
{
    private Member $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardedCompany();
        $this->me = $this->member();
    }

    public function testThePersonaUsesTheirCalendarAndFilesDuringTheCall(): void
    {
        $call = $this->liveCall($this->me);
        $calls = $this->service(CallManager::class);

        $turn = $calls->say($call, 'Could we meet on Tuesday?');
        self::assertSame('Let me check. I am free in the morning, except at ten.', $turn->reply);

        $turn = $calls->say($call, 'Then let us book Tuesday at ten.');
        self::assertSame('Let me check. I am busy then, I am afraid. Another time?', $turn->reply, 'Ten is taken by the operations review.');
        $turn = $calls->say($call, 'Let us book Tuesday at eleven, then.');
        self::assertStringContainsString('it is in my calendar', $turn->reply);

        $turn = $calls->say($call, 'Have you opened a ticket about the lost orders?');
        self::assertStringContainsString('thirty-seven orders', $turn->reply);
        $turn = $calls->say($call, 'As I said last time, we can help.');
        self::assertStringContainsString('we have spoken before', $turn->reply);

        self::assertSame(['check_calendar', 'book_meeting', 'look_up', 'previous_calls'], array_values(array_unique(array_column($call->actions(), 'tool'))));
        self::assertSame([['date' => new \DateTimeImmutable('next tuesday')->format('Y-m-d'), 'time' => '11:00', 'minutes' => 30, 'subject' => 'Workshop']], $call->bookings());
        $this->service(AgentContext::class)->forCall($call);
        self::assertSame(['meetings_booked' => $call->bookings()], $this->service(ClientActions::class)(meetingsOnly: true), 'The coach may ask for the meetings only.');
        // The debrief shows what Ana did, and her files.
        $calls->finish($call);
        $this->signIn($this->me);
        $this->client->request('GET', '/calls/'.$call->id);
        self::assertSelectorTextContains('main', 'What Ana Diaz did during the call');
        self::assertSelectorTextContains('main', 'Booked a meeting');
        self::assertSelectorTextContains('.file', 'Support tickets');
        self::assertSelectorTextContains('.file', 'T-1042 Order lost between the shop and the warehouse');
    }

    public function testAnEarlierCallIsRememberedAndItsDebriefRead(): void
    {
        $first = $this->liveCall($this->me);
        $calls = $this->service(CallManager::class);
        $calls->say($first, 'What budget have you set aside?');
        $calls->finish($first);

        $second = $this->liveCall($this->me);
        $turn = $calls->say($second, 'As I said last time, we can help.');
        self::assertStringContainsString('thirty-seven orders', $turn->reply, 'The default answer once the tool answered.');
        self::assertSame(['count' => 1], $second->actions()[0]['data']);
        $calls->finish($second);
        self::assertNotNull($second->score, 'The coach read the earlier debrief and handed in this one.');
    }

    public function testTheToolsAnswerWhatAModelGetsWrong(): void
    {
        $call = $this->liveCall($this->me);
        $this->service(AgentContext::class)->forCall($call);

        self::assertSame('No such file.', $this->service(LookUp::class)('invoices', 'march')['error']);
        self::assertSame('Give the day as YYYY-MM-DD.', $this->service(CheckCalendar::class)('2001-01-01')['error']);
        self::assertSame('Give the day as YYYY-MM-DD.', $this->service(CheckCalendar::class)('the day after the storm')['error']);
        self::assertArrayHasKey('free_hours', $this->service(CheckCalendar::class)('next monday'), 'A relative day is understood.');
        self::assertFalse($this->service(BookMeeting::class)('next tuesday', 'noonish')['booked']);
        self::assertSame([], $call->bookings());

        $offer = $this->service(ReadOffer::class);
        self::assertStringContainsString('offshore', $offer('objections'));
        self::assertStringContainsString('Nothing about "astronauts"', $offer('astronauts'));
        self::assertGreaterThan(500, mb_strlen($offer()));
    }

    public function testATooledAgentNeedsSomethingToWorkOn(): void
    {
        $context = $this->service(AgentContext::class);
        $context->reset();

        $this->expectException(\LogicException::class);
        $context->call();
    }
}
