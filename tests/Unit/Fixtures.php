<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Call;
use App\Entity\Company;
use App\Entity\Member;
use App\Entity\Scenario;
use App\Enum\Difficulty;
use App\Enum\Language;
use App\Enum\MeetingType;
use App\Enum\MemberRole;
use App\Enum\Tone;
use App\Model\Persona;
use App\Model\Situation;
use App\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Builders shared by the unit tests: members, personas, scenarios, situations, calls, and a company repository that
 * returns a given company without a database.
 *
 * @phpstan-require-extends TestCase
 */
trait Fixtures
{
    protected static function at(string $time = '2026-11-13 10:00:00'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }

    protected static function member(string $name = 'Alice Martin', MemberRole $role = MemberRole::Seller, string $email = 'alice@example.com'): Member
    {
        return new Member($name, $email, $role, self::at());
    }

    protected static function persona(?Tone $tone = null, ?string $gender = null, string $name = 'Claire Dubois'): Persona
    {
        return new Persona($name, 'CTO', 'Acme', 'Pragmatic and busy.', $gender, $tone);
    }

    /**
     * @param array<string, string> $brief
     */
    protected static function scenario(
        ?Member $author = null,
        array $brief = ['pain' => 'Their lead developer is leaving.', 'budget' => '50k a year.', 'setup' => 'Between two meetings.'],
        int $minutes = 10,
        ?Persona $persona = null,
    ): Scenario {
        return new Scenario(
            title: 'Discovery with Acme',
            type: MeetingType::Discovery,
            difficulty: Difficulty::Demanding,
            minutes: $minutes,
            context: 'Acme filled in the contact form.',
            goal: 'Get a second meeting.',
            persona: $persona ?? self::persona(),
            opening: 'Hello, Claire speaking.',
            brief: $brief,
            author: $author,
            now: self::at(),
        );
    }

    /**
     * @param list<string> $objectives
     */
    protected static function situation(string $key = 'price_request', array $objectives = ['Understand the need', 'Avoid a random price', 'Get the next meeting']): Situation
    {
        $situation = Situation::fromArray($key, [
            'label' => 'Price request on the website',
            'emoji' => '💶',
            'family' => 'prospecting',
            'direction' => 'outgoing',
            'material' => 'The form message',
            'pitch' => 'A prospect requested a price through the website and wants a number.',
            'objectives' => $objectives,
        ]);
        \assert(null !== $situation);

        return $situation;
    }

    protected static function call(?Scenario $scenario = null, ?Member $member = null, string $at = '2026-11-13 10:00:00'): Call
    {
        return new Call($member ?? self::member(), $scenario ?? self::scenario(), self::at($at));
    }

    /**
     * A done call with a score and a debrief.
     *
     * @param array<string, mixed> $debrief
     */
    protected static function doneCall(int $score, array $debrief = [], ?Scenario $scenario = null, string $at = '2026-11-13 10:00:00', ?Member $member = null): Call
    {
        $call = self::call($scenario, $member, $at);
        $call->say(\App\Enum\Speaker::Seller, 'Hello, what brings you to us?', self::at($at)->modify('+10 seconds'));
        $call->stop(\App\Enum\CallEnd::Seller, self::at($at)->modify('+5 minutes'));
        $call->close($debrief, $score);

        return $call;
    }

    protected static function company(Language $language = Language::English): Company
    {
        $company = new Company();
        $company->name = 'Etix';
        $company->language = $language;
        $company->offer = 'Dedicated development teams.';

        return $company;
    }

    /**
     * A real (final) company repository whose Doctrine plumbing is stubbed so that get() returns this company.
     */
    protected static function companies(Company $company): CompanyRepository
    {
        $persister = self::createStub(EntityPersister::class);
        $persister->method('load')->willReturn($company);
        $unitOfWork = self::createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityPersister')->willReturn($persister);
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn(new ClassMetadata(Company::class));
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return new CompanyRepository($registry);
    }
}
