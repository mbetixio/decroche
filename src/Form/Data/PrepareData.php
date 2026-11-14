<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Enum\Difficulty;
use App\Enum\MeetingType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A rehearsal of a real meeting: a prospect file, a description, or the transcript of a real call.
 */
final class PrepareData
{
    /** Whether the text is the transcript of a real call. */
    public bool $fromCall = false;

    #[Assert\NotBlank, Assert\Length(min: 15, max: 20000)]
    public string $text = '';

    public ?MeetingType $type = null;

    public Difficulty $difficulty = Difficulty::Demanding;

    #[Assert\Range(min: 3, max: 60)]
    public int $minutes = 12;
}
