<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Member;
use App\Entity\Scenario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may see and play a scenario, and who may edit or archive it (see {@see Scenario::visibleTo()}).
 *
 * @extends Voter<self::VIEW|self::EDIT, Scenario>
 */
final class ScenarioVoter extends Voter
{
    public const string VIEW = 'SCENARIO_VIEW';
    public const string EDIT = 'SCENARIO_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Scenario;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $member = $token->getUser();
        if (!$member instanceof Member) {
            return false;
        }

        return self::VIEW === $attribute ? $subject->visibleTo($member) : $subject->editableBy($member);
    }
}
