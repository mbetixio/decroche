<?php

declare(strict_types=1);

namespace App\Team;

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
            throw new \DomainException('team.error.email_taken');
        }
        $member = new Member(trim($name), $email, $role, $this->clock->now());
        $member->setPassword($this->hasher->hashPassword($member, $password));
        $member->profile = trim($profile);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    /**
     * Invites someone: a personal link to send them (the pending one, if they were already invited, with the role
     * asked now).
     *
     * @throws \DomainException when they already have an account
     */
    public function invite(string $email, MemberRole $role, ?Member $by): Invitation
    {
        if (null !== $this->members->findOneByEmail($email)) {
            throw new \DomainException('team.error.email_taken');
        }
        if (null !== $pending = $this->invitations->findPendingByEmail($email)) {
            $pending->role = $role;
            $this->em->flush();

            return $pending;
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
            throw new \DomainException('team.error.invitation_used');
        }

        // The account and the used invitation are saved together, or not at all.
        return $this->em->wrapInTransaction(function () use ($invitation, $name, $password): Member {
            $member = $this->create($name, $invitation->email, $password, $invitation->role);
            $invitation->accept($this->clock->now());
            $this->em->flush();

            return $member;
        });
    }

    public function withdraw(Invitation $invitation): void
    {
        $this->em->remove($invitation);
        $this->em->flush();
    }

    /**
     * Makes a seller a manager, or a manager a seller.
     *
     * @throws \DomainException when a manager tries to change their own role (so the team always keeps a manager)
     */
    public function toggleRole(Member $member, Member $by): void
    {
        if ($member === $by) {
            throw new \DomainException('team.error.own_role');
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
        // A new password closes every other way in: the MCP key has to be created again.
        $member->forgetMcpKey();
        $this->em->flush();
    }

    /**
     * Saves changes made to a member (their name, their profile).
     */
    public function save(Member $member): void
    {
        $this->em->persist($member);
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
