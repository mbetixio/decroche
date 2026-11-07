<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Call;
use App\Entity\Member;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may see a call (its member, and managers following the team) and who may play it (its member only).
 *
 * @extends Voter<self::*, Call>
 */
final class CallVoter extends Voter
{
    public const string VIEW = 'CALL_VIEW';
    public const string PLAY = 'CALL_PLAY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::PLAY], true) && $subject instanceof Call;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();
        if (!$member instanceof Member) {
            return false;
        }

        return $subject->member === $member || (self::VIEW === $attribute && $member->isManager());
    }
}
