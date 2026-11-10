<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\Call;
use App\Entity\Member;
use App\Enum\BriefSection;
use App\Enum\CallEnd;
use App\Enum\Language;
use App\Enum\MemberRole;
use App\Enum\Speaker;
use App\Model\Situation;
use App\Repository\MemberRepository;
use App\Scenario\ScenarioFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The example team and its debriefed calls (config/decroche/{language}/calls.yaml): with them, a demo instance shows
 * a team's progress, the manager's view and the MCP statistics from the first sign-in. The calls are real ones, played
 * and debriefed on Décroche; their dates count back from today.
 *
 * @phpstan-type DemoPerson array{name: string, email?: string, profile: string}
 * @phpstan-type DemoCall array{seller: string, days_ago: int, scenario: array{situation?: array{key: string}&array<string, mixed>}&array<string, mixed>, lines: list<array{speaker: string, text: string, second: int, voice_ms?: int}>, ended_by: string, seconds: int, revealed: list<string>, score: int, debrief: array<string, mixed>}
 */
final readonly class DemoCalls
{
    public function __construct(
        private MemberRepository $members,
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/config/decroche')]
        private string $directory,
    ) {
    }

    /**
     * Adds the example sellers (unless their address is taken) and the team's calls, the given manager's among them:
     * they take the example manager's name and profile. Returns how many calls were added.
     */
    public function load(Language $language, Member $manager): int
    {
        /** @var array{team: array<string, DemoPerson>, calls: non-empty-list<DemoCall>} $demo */
        $demo = Yaml::parseFile(\sprintf('%s/%s/calls.yaml', $this->directory, $language->value));
        $today = $this->clock->now()->setTime(10, 0);
        $joined = $today->modify(\sprintf('-%d days', max(array_column($demo['calls'], 'days_ago')) + 7));

        $team = ['manager' => $manager];
        foreach ($demo['team'] as $key => $person) {
            if (!isset($person['email'])) {
                // The example manager: the one who signs in takes their place.
                $manager->name = $person['name'];
                $manager->profile = $person['profile'];
                continue;
            }
            $team[$key] = $this->members->findOneByEmail($person['email']) ?? $this->seller($person['name'], $person['email'], $person['profile'], $joined);
        }

        foreach ($demo['calls'] as $i => $data) {
            $this->call($team[$data['seller']] ?? $manager, $data, $today->modify(\sprintf('-%d days +%d minutes', $data['days_ago'], 47 * $i)));
        }
        $this->em->flush();

        return \count($demo['calls']);
    }

    /**
     * A seller of the example team. They never sign in (a random password): the manager sees them in the team view.
     */
    private function seller(string $name, string $email, string $profile, \DateTimeImmutable $joined): Member
    {
        $member = new Member($name, $email, MemberRole::Seller, $joined);
        $member->setPassword($this->hasher->hashPassword($member, bin2hex(random_bytes(16))));
        $member->profile = $profile;
        $this->em->persist($member);

        return $member;
    }

    /**
     * One call, replayed line by line at its original pace, then closed with the coach's debrief.
     *
     * @param DemoCall $data
     */
    private function call(Member $seller, array $data, \DateTimeImmutable $start): void
    {
        $given = $data['scenario'];
        $scenario = ScenarioFactory::fromArray($given, $seller, $start->modify('-3 minutes'));
        if (isset($given['situation'])) {
            $scenario->setSituation(Situation::fromArray($given['situation']['key'], $given['situation']));
        }
        $this->em->persist($scenario);

        $call = new Call($seller, $scenario, $start);
        foreach ($data['lines'] as $line) {
            $call->say(Speaker::from($line['speaker']), $line['text'], $start->modify(\sprintf('+%d seconds', $line['second'])), $line['voice_ms'] ?? null);
        }
        foreach ($data['revealed'] as $section) {
            $call->reveal(BriefSection::from($section));
        }
        $call->stop(CallEnd::from($data['ended_by']), $start->modify(\sprintf('+%d seconds', $data['seconds'])));
        $call->close($data['debrief'], $data['score']);
        $this->em->persist($call);
    }
}
