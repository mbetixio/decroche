<?php

declare(strict_types=1);

namespace App\Agent;

use App\Entity\Call;
use App\Entity\Member;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What an agent is working on, for its tools: the call the persona is in or the coach grades, or the seller a scenario
 * is written for. Set just before the agent runs; a tool used outside of it says so to the model.
 */
final class AgentContext implements ResetInterface
{
    private ?Call $call = null;
    private ?Member $member = null;

    public function forCall(Call $call): void
    {
        $this->call = $call;
        $this->member = $call->member;
    }

    public function forMember(Member $member): void
    {
        $this->call = null;
        $this->member = $member;
    }

    /**
     * @throws \LogicException when no call is being worked on
     */
    public function call(): Call
    {
        return $this->call ?? throw new \LogicException('No call is in progress.');
    }

    /**
     * @throws \LogicException when no seller is being worked for
     */
    public function member(): Member
    {
        return $this->member ?? throw new \LogicException('No seller is concerned.');
    }

    public function reset(): void
    {
        $this->call = null;
        $this->member = null;
    }
}
