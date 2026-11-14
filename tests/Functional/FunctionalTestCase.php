<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Demo\DemoCompany;
use App\Entity\Call;
use App\Entity\Member;
use App\Enum\Language;
use App\Enum\MemberRole;
use App\Repository\CompanyRepository;
use App\Scenario\SurpriseCalls;
use App\Team\TeamManager;
use App\Tests\Fake\ScriptedModel;
use App\Tests\Fake\SilentSynthesizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A fresh database for every test, and helpers to set up the instance: the example company, members, sign-in.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = $this->service(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        // Foreign keys are enforced: they are set aside while the tables are dropped and created again.
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = OFF');
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        $em->clear();
        ScriptedModel::reset();
        SilentSynthesizer::reset();
        // Every test starts with the members' model quotas untouched.
        $this->service('cache.rate_limiter')->clear();
    }

    /**
     * The example company, set up and onboarded, in the given working language.
     */
    protected function onboardedCompany(Language $language = Language::English): void
    {
        $company = $this->service(CompanyRepository::class)->get();
        $company->language = $language;
        $demo = $this->service(DemoCompany::class);
        $demo->apply($company);
        $demo->loadLibrary($language);
        $company->completeOnboarding($this->service(ClockInterface::class)->now());
        $this->service(EntityManagerInterface::class)->flush();
    }

    protected function member(string $email = 'seller@decroche.test', MemberRole $role = MemberRole::Seller, string $profile = 'Junior seller, two years in software services.'): Member
    {
        return $this->service(TeamManager::class)->create(ucfirst(strstr($email, '@', true) ?: 'Member').' Test', $email, 'password-123', $role, $profile);
    }

    protected function manager(): Member
    {
        return $this->member('manager@decroche.test', MemberRole::Manager, 'Sales manager.');
    }

    /**
     * A live call of the member on a freshly written price request, started at the given time (now by default). Each
     * request boots the kernel again: the member is read from the current entity manager.
     */
    protected function liveCall(Member $member, ?\DateTimeImmutable $startedAt = null): Call
    {
        $em = $this->service(EntityManagerInterface::class);
        $member = $em->find(Member::class, $member->id) ?? $member;
        $call = new Call($member, $this->service(SurpriseCalls::class)->draw($member, 'price_request'), $startedAt ?? $this->service(ClockInterface::class)->now());
        $em->persist($call);
        $em->flush();

        return $call;
    }

    /**
     * The CSRF token of a call's forms, as its page gives it (the phone screen while live, the hang-up forms after).
     */
    protected function callToken(Call $call): string
    {
        $crawler = $this->client->request('GET', '/calls/'.$call->id);
        $node = $crawler->filter('[data-call-token-value]');

        return $node->count() > 0 ? (string) $node->attr('data-call-token-value') : (string) $crawler->filter('form[action$="/abandon"] input[name=_token], form[action$="/finish"] input[name=_token]')->attr('value');
    }

    /**
     * The JSON lines of the last streamed answer (a call's exchange, a waiting screen's steps).
     *
     * @return list<array<string, mixed>>
     */
    protected function lines(): array
    {
        $lines = array_values(array_filter(explode("\n", (string) $this->client->getInternalResponse()->getContent())));

        return array_map(static fn (string $line): array => json_decode($line, true, flags: \JSON_THROW_ON_ERROR), $lines);
    }

    /**
     * Signs the member in (fetched again, since the entity manager may have been reset by a request).
     */
    protected function signIn(Member $member): Member
    {
        $member = $this->service(EntityManagerInterface::class)->getRepository(Member::class)->findOneBy(['email' => $member->email]) ?? $member;
        $this->client->loginUser($member);

        return $member;
    }

    /**
     * A CSRF token stored in the client's session, for a request no page offers a form for. A page must have been
     * requested first.
     */
    protected function csrfToken(string $id): string
    {
        $request = $this->client->getRequest();
        $container = $this->client->getContainer();
        $container->get('request_stack')->push($request);
        $token = (string) $container->get('security.csrf.token_manager')->getToken($id)->getValue();
        $request->getSession()->save();

        return $token;
    }

    /**
     * Submits the form that holds the given button on the current page.
     *
     * @param array<string, mixed> $values
     */
    protected function submit(string $button, array $values = []): void
    {
        $this->client->submit($this->client->getCrawler()->selectButton($button)->form(), $values);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
