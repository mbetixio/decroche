<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ai\Output;

use App\Ai\Output\CoachReport;
use App\Ai\Output\CriterionGrade;
use App\Ai\Output\MissedSection;
use App\Ai\Output\ObjectiveVerdict;
use App\Ai\Output\QuotedLine;
use PHPUnit\Framework\TestCase;

final class CoachReportTest extends TestCase
{
    public function testItReadsAsTheArrayTheDebriefBuilderExpects(): void
    {
        $report = new CoachReport(
            criteria: [new CriterionGrade('discovery', 7, 'Good questions.'), new CriterionGrade('closing', 3, 'No next step.')],
            summary: 'A short call.',
            nextStep: true,
            outcome: 'Still open.',
            result: 'Meeting booked',
            objectives: [new ObjectiveVerdict('Book a meeting', 'reached', 'Tuesday 10am.')],
            discovered: ['budget'],
            missed: [new MissedSection('pain', 'Lost orders', 'What happens when an order goes wrong?')],
            best: [new QuotedLine('Shall we say Tuesday?', 'Direct.', '')],
            worst: [],
            speech: '',
            tips: ['Ask about the pain first.'],
        );

        $data = $report->toArray();

        self::assertSame(['key' => 'closing', 'score' => 3, 'comment' => 'No next step.'], $data['criteria']['closing']);
        self::assertSame(['discovery', 'closing'], array_keys($data['criteria']));
        self::assertTrue($data['next_step']);
        self::assertArrayNotHasKey('nextStep', $data);
        self::assertSame([['rubric' => 'pain', 'what' => 'Lost orders', 'how' => 'What happens when an order goes wrong?']], $data['missed']);
        self::assertSame('Shall we say Tuesday?', $data['best'][0]['quote']);
    }
}
