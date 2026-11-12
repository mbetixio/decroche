<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Call;
use App\Entity\Member;
use App\Enum\Language;
use App\Enum\Speaker;
use App\Onboarding\OnboardingStep;
use App\Repository\CompanyRepository;
use App\Scenario\SurpriseCalls;
use App\Tests\Fake\ScriptedModel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * The long pieces of work, followed step by step: each step the agents really take comes as a line, labelled in the
 * user's language, then where to go, or what went wrong.
 */
final class ProgressTest extends FunctionalTestCase
{
    private const array FOLLOW = ['HTTP_ACCEPT' => 'application/x-ndjson'];

    public function testADrawTellsItsSteps(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->member());
        $crawler = $this->client->request('GET', '/');
        $this->client->request('POST', '/call/new', ['token' => $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value'), 'situation' => 'price_request'], server: self::FOLLOW);

        self::assertResponseHeaderSame('Content-Type', 'application/x-ndjson; charset=utf-8');
        [$steps, $end] = $this->events();
        self::assertSame(['draw.situation', 'writer.tool.recent_calls', 'writer.tool.read_offer', 'writer.writing'], array_column($steps, 'key'));
        self::assertSame('Situation drawn: Pricing request on the website', $steps[0]['label']);
        self::assertSame('Checking your latest calls, to make this one new', $steps[1]['label']);
        self::assertSame('done', $end['t']);
        self::assertMatchesRegularExpression('#^/call/\d+$#', $end['url']);

        ScriptedModel::$unreachable = true;
        $this->client->request('POST', '/call/new', ['token' => $crawler->filter('form[action="/call/new"] input[name=token]')->attr('value')], server: self::FOLLOW);
        [, $end] = $this->events();
        self::assertSame('error', $end['t']);
        self::assertNotSame('', $end['error']);
    }

    public function testADebriefTellsTheCoachsSteps(): void
    {
        $this->onboardedCompany();
        $me = $this->signIn($this->member());
        $call = $this->live($me);
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $token = (string) $crawler->filter('form[action$="/finish"] input[name=_token]')->attr('value');

        $this->client->request('POST', '/calls/'.$call->id.'/finish', ['_token' => $token], server: self::FOLLOW);
        [$steps, $end] = $this->events();
        self::assertSame(['call.hung_up', 'coach.tool.previous_debriefs', 'coach.tool.find_in_transcript', 'coach.tool.client_actions', 'coach.tool.read_offer', 'coach.writing'], array_column($steps, 'key'));
        self::assertSame(['t' => 'done', 'url' => '/calls/'.$call->id], $end);
    }

    public function testAFailedDebriefStillLeadsToTheCall(): void
    {
        $this->onboardedCompany();
        $me = $this->signIn($this->member());
        $call = $this->live($me);
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $token = (string) $crawler->filter('form[action$="/finish"] input[name=_token]')->attr('value');
        ScriptedModel::$unreachable = true;

        $this->client->request('POST', '/calls/'.$call->id.'/finish', ['_token' => $token], server: self::FOLLOW);
        [, $end] = $this->events();
        self::assertSame('error', $end['t']);
        self::assertSame('/calls/'.$call->id, $end['url'], 'The call page offers to write it again.');
    }

    public function testARehearsalTellsItsStepsOrSendsTheFormBack(): void
    {
        $this->onboardedCompany();
        $this->signIn($this->member());
        $form = $this->client->request('GET', '/prepare')->selectButton('Write the rehearsal')->form();

        $this->client->request('POST', '/prepare', $form->getPhpValues(), server: self::FOLLOW);
        self::assertResponseStatusCodeSame(422, 'An empty form goes back to the page, to be corrected.');

        $values = $form->getPhpValues();
        $values['prepare']['text'] = 'Tomorrow 10am, Ana Diaz, operations director at Freshcart, asked for our rates.';
        $this->client->request('POST', '/prepare', $values, server: self::FOLLOW);
        [$steps, $end] = $this->events();
        self::assertSame(['prepare.reading', 'writer.tool.recent_calls', 'writer.tool.read_offer', 'writer.writing'], array_column($steps, 'key'));
        self::assertSame('done', $end['t']);

        ScriptedModel::$garbled = 2;
        $this->client->request('POST', '/prepare', $values, server: self::FOLLOW);
        [, $end] = $this->events();
        self::assertSame('The rehearsal could not be written (the model is unreachable): try again.', $end['error'] ?? null);
    }

    public function testTheSiteAnalysisTellsItsSteps(): void
    {
        $manager = $this->manager();
        $company = $this->service(CompanyRepository::class)->get();
        $company->name = 'Acme';
        $company->about = 'Acme builds software for retailers and logistics firms across Europe.';
        $company->language = Language::English;
        $company->reach(OnboardingStep::Analysis);
        $this->service(EntityManagerInterface::class)->flush();
        $this->signIn($manager);
        $token = (string) $this->client->request('GET', '/welcome/analysis')->filter('[data-analysis-token-value]')->attr('data-analysis-token-value');

        $this->client->request('POST', '/welcome/analysis/run', ['token' => $token], server: self::FOLLOW);
        [$steps, $end] = $this->events();
        self::assertSame(['analysis.site', 'analysis.offer', 'analysis.situations'], array_column($steps, 'key'));
        self::assertSame('Reading —', $steps[0]['label'], 'No website: a description only.');
        self::assertSame(['t' => 'done', 'url' => '/welcome/offer'], $end);

        ScriptedModel::$unreachable = true;
        $this->client->request('POST', '/welcome/analysis/run', ['token' => $token], server: self::FOLLOW);
        [, $end] = $this->events();
        self::assertStringContainsString('try again', $end['error']);
    }

    /**
     * The steps of the last streamed work, and its last line.
     *
     * @return array{list<array{t: string, key: string, label: string}>, array<string, mixed>}
     */
    private function events(): array
    {
        $lines = array_map(static fn (string $l): array => json_decode($l, true, flags: \JSON_THROW_ON_ERROR), array_values(array_filter(explode("\n", (string) $this->client->getInternalResponse()->getContent()))));
        $end = array_pop($lines);

        return [$lines, $end];
    }

    /**
     * A live call where the seller has said something: the coach has a call to grade.
     */
    private function live(Member $me): Call
    {
        $em = $this->service(EntityManagerInterface::class);
        $me = $em->find(Member::class, $me->id);
        $call = new Call($me, $this->service(SurpriseCalls::class)->draw($me, 'price_request'), $this->service(ClockInterface::class)->now());
        $call->say(Speaker::Seller, 'What budget have you set aside?', $this->service(ClockInterface::class)->now());
        $em->persist($call);
        $em->flush();

        return $call;
    }
}
