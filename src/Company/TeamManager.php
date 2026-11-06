<?php

declare(strict_types=1);

namespace App\Company;

use App\Entity\Invitation;
use App\Entity\Member;
use App\Enum\MemberRole;
use App\Repository\InvitationRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The team: accounts, invitations, roles, passwords.
 */
final readonly class TeamManager
{
    public function __construct(
        private MemberRepository $members,
        private InvitationRepository $invitations,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Creates an account.
     *
     * @throws \DomainException when the email is taken
     */
    public function create(string $name, string $email, string $password, MemberRole $role, string $profile = ''): Member
    {
        if (null !== $this->members->findOneByEmail($email)) {
            throw new \DomainException('Un compte existe déjà avec cette adresse.');
        }
        $member = new Member(trim($name), $email, $role, $this->clock->now());
        $member->setPassword($this->hasher->hashPassword($member, $password));
        $member->profile = trim($profile);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    /**
     * Invites someone: a personal link to send them.
     *
     * @throws \DomainException when they already have an account
     */
    public function invite(string $email, MemberRole $role, ?Member $by): Invitation
    {
        if (null !== $this->members->findOneByEmail($email)) {
            throw new \DomainException('Un compte existe déjà avec cette adresse.');
        }
        foreach ($this->invitations->pending() as $pending) {
            if ($pending->email === mb_strtolower(trim($email))) {
                return $pending;
            }
        }
        $invitation = new Invitation($email, $role, $by, $this->clock->now());
        $this->em->persist($invitation);
        $this->em->flush();

        return $invitation;
    }

    /**
     * Joins the team through an invitation.
     *
     * @throws \DomainException when the invitation was already used or the email is taken
     */
    public function accept(Invitation $invitation, string $name, string $password): Member
    {
        if (!$invitation->isPending()) {
            throw new \DomainException('Cette invitation a déjà servi.');
        }
        $member = $this->create($name, $invitation->email, $password, $invitation->role);
        $invitation->accept($this->clock->now());
        $this->em->flush();

        return $member;
    }

    public function withdraw(Invitation $invitation): void
    {
        $this->em->remove($invitation);
        $this->em->flush();
    }

    /**
     * Makes a seller a manager, or a manager a seller.
     *
     * @throws \DomainException when a manager tries to change their own role, or the team would lose its last manager
     */
    public function toggleRole(Member $member, Member $by): void
    {
        if ($member === $by) {
            throw new \DomainException('Vous ne pouvez pas changer votre propre rôle.');
        }
        $member->role = $member->isManager() ? MemberRole::Seller : MemberRole::Manager;
        $this->em->flush();
    }

    /**
     * A new temporary password, shown once to the manager.
     */
    public function resetPassword(Member $member): string
    {
        $password = implode('-', str_split(bin2hex(random_bytes(6)), 4));
        $this->changePassword($member, $password);

        return $password;
    }

    public function changePassword(Member $member, string $password): void
    {
        $member->setPassword($this->hasher->hashPassword($member, $password));
        $this->em->flush();
    }

    public function save(Member $member): void
    {
        $this->em->flush();
    }

    /**
     * A new personal MCP key, shown once.
     */
    public function newMcpKey(Member $member): string
    {
        $key = $member->newMcpKey();
        $this->em->flush();

        return $key;
    }
}
