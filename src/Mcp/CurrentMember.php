<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Call\CallManager;
use App\Entity\Call;
use App\Entity\Member;
use App\Repository\CallRepository;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The member the MCP client acts for (authenticated by their personal key), and their calls.
 */
final readonly class CurrentMember
{
    public function __construct(
        private Security $security,
        private CallRepository $calls,
        private CallManager $callManager,
    ) {
    }

    /**
     * @throws ToolCallException when the client did not sign in with a member's key
     */
    public function member(): Member
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? $user : throw new ToolCallException('Connect with your personal MCP address (/mcp?key=…), shown on the "My account" page.');
    }

    /**
     * @throws ToolCallException when the member is not a manager
     */
    public function manager(): Member
    {
        $member = $this->member();

        return $member->isManager() ? $member : throw new ToolCallException('This tool is for managers only.');
    }

    /**
     * One of the member's calls: the one asked for, else the one in progress, else the latest.
     *
     * @throws ToolCallException when there is none
     */
    public function call(int $id = 0): Call
    {
        $member = $this->member();
        if ($id > 0) {
            $call = $this->calls->find($id);
            if (!$call instanceof Call || $call->member !== $member) {
                throw new ToolCallException(\sprintf('No call #%d in your name.', $id));
            }
            $this->callManager->expire($call);

            return $call;
        }

        return $this->callManager->current($member)
            ?? $this->calls->latest($member)
            ?? throw new ToolCallException('No call yet: start one with new_call.');
    }
}
