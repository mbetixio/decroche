<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\Member;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A mission a manager gives.
 */
final class MissionData
{
    /** Null: the whole team. */
    public ?Member $assignee = null;

    /** A situation key; null: any. */
    public ?string $situation = null;

    #[Assert\Range(min: 1, max: 20)]
    public int $calls = 3;

    #[Assert\Range(min: 0, max: 100)]
    public int $target = 70;

    #[Assert\GreaterThanOrEqual('today')]
    public ?\DateTimeImmutable $dueAt = null;

    #[Assert\Length(max: 200)]
    public string $note = '';
}
