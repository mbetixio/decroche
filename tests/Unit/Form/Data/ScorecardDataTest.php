<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Data;

use App\Entity\Company;
use App\Enum\Language;
use App\Form\Data\ScorecardData;
use App\Model\Criterion;
use PHPUnit\Framework\TestCase;

/**
 * The scorecard as edited by a manager: read from the company, and turned into criteria keyed by a unique slug.
 */
final class ScorecardDataTest extends TestCase
{
    public function testOfTheDefaultScorecard(): void
    {
        $company = new Company();
        $company->language = Language::English;
        $data = ScorecardData::of($company);

        self::assertCount(6, $data->criteria);
        self::assertSame('Needs discovery', $data->criteria[0]['label']);
    }

    public function testToCriteria(): void
    {
        $data = new ScorecardData();
        $data->criteria = [
            ['label' => ' Écoute active ', 'hint' => ' lets them talk '],
            ['label' => 'Closing', 'hint' => ''],
            ['label' => 'closing!', 'hint' => 'again'],
            ['label' => 'Rapport', 'hint' => ''],
        ];
        $criteria = $data->toCriteria();

        self::assertSame(['Écoute active', 'Closing', 'closing!', 'Rapport'], array_map(static fn (Criterion $c): string => $c->label, $criteria));
        self::assertSame('lets them talk', $criteria[0]->hint);
        self::assertSame(['closing', 'closing_', 'rapport'], array_map(static fn (Criterion $c): string => $c->key, \array_slice($criteria, 1)));
        self::assertSame('', $criteria[3]->hint);
    }

    public function testRoundTripThroughTheCompany(): void
    {
        $data = new ScorecardData();
        $data->criteria = [['label' => 'Rapport', 'hint' => 'trust'], ['label' => 'Closing', 'hint' => 'next step']];
        $company = new Company();
        $company->setScorecard($data->toCriteria());

        self::assertSame($data->criteria, ScorecardData::of($company)->criteria);
    }
}
