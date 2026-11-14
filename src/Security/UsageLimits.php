<?php

declare(strict_types=1);

namespace App\Security;

use App\Call\CallException;
use App\Entity\Member;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Keeps one member from running up the instance's model bill: a few dozen calls written or debriefed an hour, a steady
 * conversation pace. The same quotas apply on the website and over MCP.
 */
final readonly class UsageLimits
{
    public function __construct(
        #[Autowire(service: 'limiter.ai_writing')]
        private RateLimiterFactoryInterface $writing,
        #[Autowire(service: 'limiter.ai_talking')]
        private RateLimiterFactoryInterface $talking,
        #[Autowire(service: 'limiter.ai_coaching')]
        private RateLimiterFactoryInterface $coaching,
    ) {
    }

    /**
     * Counts one more use of the model by the member.
     *
     * @throws CallException when their quota for this kind of use is spent for now
     */
    public function consume(Member $member, Usage $usage): void
    {
        $factory = match ($usage) {
            Usage::Writing => $this->writing,
            Usage::Talking => $this->talking,
            Usage::Coaching => $this->coaching,
        };
        if (!$factory->create('member-'.$member->id)->consume()->isAccepted()) {
            throw new CallException('call.error.limit');
        }
    }
}
