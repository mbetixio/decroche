<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Entity\Call;
use App\Entity\Member;
use App\Enum\MemberRole;
use App\Progress\MissionTracker;
use App\Progress\TeamReport;
use App\Repository\CompanyRepository;
use App\Repository\MemberRepository;
use App\Team\MissionManager;
use App\Team\TeamManager;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Elicitation\NumberSchemaDefinition;
use Mcp\Schema\Elicitation\StringSchemaDefinition;
use Mcp\Schema\Elicitation\TitledEnumSchemaDefinition;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\ClientGateway;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a manager's AI can do: read the team board and a seller's figures, give missions, invite people. What changes
 * the team is shown to the manager as a form first, where their client can show one: the AI proposes, they decide.
 */
final readonly class TeamTools
{
    public function __construct(
        private CurrentMember $me,
        private TeamReport $board,
        private MissionTracker $missions,
        private ProgressTools $progress,
        private MemberRepository $members,
        private CompanyRepository $companies,
        private MissionManager $missionManager,
        private TeamManager $teamManager,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'team', title: 'The team', description: 'For managers: every member with the difficulty of their calls, calls, average score, trend, weakest criterion and last activity; the results by situation; the missions in progress; the latest calls.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function team(): array
    {
        $this->me->manager();
        $board = $this->board->build();

        return [
            'members' => array_map(static fn (array $row): array => [
                'name' => $row['member']->name, 'email' => $row['member']->email, 'role' => $row['member']->role->value,
                'difficulty' => $row['stats']->challenge->difficulty->name, 'calls' => $row['stats']->done, 'average' => $row['stats']->average,
                'trend' => $row['stats']->trend, 'weakest' => $row['stats']->weakest,
                'last_call' => $row['lastAt']?->format('Y-m-d'),
            ], $board['members']),
            'situations' => $board['situations'],
            'missions' => array_map(static fn (array $row): array => [
                'id' => $row['mission']->id, 'for' => $row['mission']->assignee->name ?? 'everyone', 'situation' => $row['mission']->situation,
                'calls' => $row['mission']->calls, 'target_score' => $row['mission']->target, 'due' => $row['mission']->dueAt?->format('Y-m-d'),
                'progress' => array_map(static fn (array $p): string => $p['member']->name.': '.$p['done'].'/'.$row['mission']->calls, $row['members']),
            ], $this->missions->team()),
            'recent' => array_map(static fn (Call $c): array => [
                'call' => $c->id, 'member' => $c->member->name, 'scenario' => $c->scenario->title, 'date' => $c->startedAt->format('Y-m-d H:i'), 'score' => $c->score,
            ], $board['recent']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'member_progress', title: 'A member\'s progress', description: 'For managers: one member\'s full figures (as my_progress gives them), found by e-mail address.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function memberProgress(string $email): array
    {
        $this->me->manager();
        $member = $this->member($email);

        return ['member' => $member->name, 'profile' => $member->profile] + $this->progress->report($member);
    }

    /**
     * @param string $email     the member's e-mail address, or empty for the whole team
     * @param string $situation a situation key (see the situations tool), or empty for any
     * @param int    $calls     how many calls
     * @param int    $target    the minimum score of each call, out of 100
     * @param string $due       the deadline (YYYY-MM-DD), or empty
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'give_mission', title: 'Give a mission', description: 'For managers: gives a member (or the whole team) a mission, so many calls of a situation scored at least so much, before a date. It shows on their home page and in their my_progress. If your client can show forms, the manager confirms or adjusts it first.')]
    public function giveMission(ClientGateway $client, string $email = '', string $situation = '', int $calls = 3, int $target = 60, string $due = '', string $note = ''): array
    {
        $manager = $this->me->manager();
        $assignee = '' !== $email ? $this->member($email) : null;
        if ('' !== $situation && null === $this->companies->get()->situation($situation)) {
            throw new ToolCallException(\sprintf('No situation "%s": the situations tool lists them.', $situation));
        }
        $answers = Ask::form($client, \sprintf('Give %s this mission?', $assignee->name ?? 'the whole team').('' !== $situation ? ' Situation: '.$this->companies->get()->situation($situation)?->label.'.' : ''), new ElicitationSchema([
            'calls' => new NumberSchemaDefinition('Calls to make', integerOnly: true, default: max(1, min(20, $calls)), minimum: 1, maximum: 20),
            'target' => new NumberSchemaDefinition('Minimum score of each call, out of 100', integerOnly: true, default: max(0, min(100, $target)), minimum: 0, maximum: 100),
            'due' => new StringSchemaDefinition('Deadline', 'YYYY-MM-DD, or empty for none', default: $due, format: 'date'),
            'note' => new StringSchemaDefinition('A word for them', default: $note, maxLength: 500),
        ]));
        if (null !== $answers) {
            $calls = \is_numeric($answers['calls'] ?? null) ? (int) $answers['calls'] : $calls;
            $target = \is_numeric($answers['target'] ?? null) ? (int) $answers['target'] : $target;
            $due = \is_string($answers['due'] ?? null) ? trim($answers['due']) : $due;
            $note = \is_string($answers['note'] ?? null) ? trim($answers['note']) : $note;
        }
        $dueAt = '' !== $due ? \DateTimeImmutable::createFromFormat('!Y-m-d', $due) : null;
        if (false === $dueAt || (null !== $dueAt && ($dueAt->format('Y-m-d') !== $due || $dueAt < $this->clock->now()->setTime(0, 0)))) {
            throw new ToolCallException('The deadline must be a future date, as YYYY-MM-DD.');
        }
        $mission = $this->missionManager->give($assignee, '' !== $situation ? $situation : null, max(1, min(20, $calls)), max(0, min(100, $target)), $dueAt, $note, $manager);

        return ['mission' => $mission->id, 'for' => $mission->assignee->name ?? 'everyone', 'calls' => $mission->calls, 'target_score' => $mission->target, 'due' => $mission->dueAt?->format('Y-m-d')];
    }

    /**
     * @param string $role seller or manager
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'invite', title: 'Invite someone', description: 'For managers: invites someone to the team. Returns the invitation link to send them; they choose their password and describe themselves. If your client can show forms, the manager confirms the address and the role first.')]
    public function invite(ClientGateway $client, string $email, string $role = 'seller'): array
    {
        $manager = $this->me->manager();
        $answers = Ask::form($client, 'Invite this person to the team? They will get a link to join.', new ElicitationSchema([
            'email' => new StringSchemaDefinition('E-mail address', default: trim($email), format: 'email'),
            'role' => new TitledEnumSchemaDefinition('Role', array_map(fn (MemberRole $r): array => ['const' => $r->value, 'title' => $this->translator->trans('enum.member_role.'.$r->value, locale: 'en')], MemberRole::cases()), default: (MemberRole::tryFrom($role) ?? MemberRole::Seller)->value),
        ], ['email']));
        if (null !== $answers) {
            $email = \is_string($answers['email'] ?? null) ? $answers['email'] : $email;
            $role = \is_string($answers['role'] ?? null) ? $answers['role'] : $role;
        }
        if (false === filter_var(trim($email), \FILTER_VALIDATE_EMAIL)) {
            throw new ToolCallException(\sprintf('"%s" is not an e-mail address.', $email));
        }
        try {
            $invitation = $this->teamManager->invite($email, MemberRole::tryFrom($role) ?? MemberRole::Seller, $manager);
        } catch (\DomainException $e) {
            throw new ToolCallException($this->translator->trans($e->getMessage(), domain: 'messages', locale: 'en'));
        }

        return ['email' => $invitation->email, 'role' => $invitation->role->value, 'link' => $this->urls->generate('join', ['token' => $invitation->token], UrlGeneratorInterface::ABSOLUTE_URL)];
    }

    private function member(string $email): Member
    {
        return $this->members->findOneByEmail(mb_strtolower(trim($email))) ?? throw new ToolCallException(\sprintf('No member "%s": the team tool lists them.', $email));
    }
}
