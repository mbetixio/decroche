<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Data;

use App\Entity\Company;
use App\Form\Data\SituationData;
use App\Form\Data\SituationsData;
use App\Tests\Unit\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue as edited by a manager: read from the company, and the situations kept (removed or incomplete ones
 * dropped).
 */
final class SituationsDataTest extends TestCase
{
    use Fixtures;

    public function testOfAndKeptRoundTrip(): void
    {
        $company = new Company();
        $company->setSituations([self::situation('price_request'), self::situation('cold_call')]);
        $data = SituationsData::of($company);

        self::assertCount(2, $data->situations);
        self::assertSame("Understand the need\nAvoid a random price\nGet the next meeting", $data->situations[0]->objectives);
        self::assertEquals(array_values($company->situations()), $data->kept());
    }

    public function testKeptDropsRemovedAndIncompleteSituations(): void
    {
        $company = new Company();
        $company->setSituations([self::situation('price_request'), self::situation('cold_call')]);
        $data = SituationsData::of($company);
        $data->situations[0]->keep = false;
        $incomplete = new SituationData();
        $incomplete->label = 'Too short';
        $incomplete->pitch = 'Short.';
        $incomplete->objectives = 'One';
        $new = new SituationData();
        $new->label = 'Angry client';
        $new->pitch = 'A client calls, angry about an invoice.';
        $new->objectives = "- Listen\n- Calm them down";
        $data->situations[] = $incomplete;
        $data->situations[] = $new;
        $kept = $data->kept();

        self::assertSame(['cold_call', 'angry_client'], array_map(static fn ($s): string => $s->key, $kept));
        self::assertSame(['Listen', 'Calm them down'], $kept[1]->objectives);
    }
}
