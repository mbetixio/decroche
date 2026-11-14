<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Workspace;
use PHPUnit\Framework\TestCase;

final class WorkspaceTest extends TestCase
{
    private static function workspace(): Workspace
    {
        return Workspace::fromArray([
            'calendar' => [
                ['when' => '2026-11-17 10:00', 'minutes' => 60, 'what' => 'Operations review'],
                ['when' => '2026-11-17 14:00', 'minutes' => 90, 'what' => 'Warehouse visit'],
                ['when' => 'next week', 'minutes' => 60, 'what' => 'Not a date: left out'],
                ['when' => '2026-11-21 10:00', 'minutes' => 60, 'what' => 'A Saturday: left out'],
            ],
            'sources' => [
                ['key' => 'Stock levels', 'label' => 'Stock', 'description' => 'What the shop holds.', 'entries' => [
                    ['title' => 'SKU-12 Blue kettle', 'when' => '', 'details' => [['name' => 'Quantity', 'value' => '4'], ['name' => 'Supplier', 'value' => 'Kettlco']], 'note' => 'Reorder pending.'],
                    ['title' => 'SKU-40 Red toaster', 'when' => '', 'details' => [['name' => 'Quantity', 'value' => '0'], ['name' => 'Supplier', 'value' => 'Toastly']], 'note' => 'Out of stock since Monday.'],
                ]],
                ['key' => '!!!', 'label' => 'No key', 'entries' => []],
            ],
        ]);
    }

    public function testSourcesAreKeyedAndRecordsSearched(): void
    {
        $workspace = self::workspace();

        self::assertSame(['stock_levels'], array_keys($workspace->sources()), 'Keys are made snake_case; a source without one is left out.');
        self::assertSame('SKU-40 Red toaster', $workspace->search('stock_levels', 'toaster out of stock')[0]['title']);
        self::assertSame('SKU-12 Blue kettle', $workspace->search('stock_levels', 'kettlco')[0]['title'], 'Any field is searched.');
        self::assertCount(2, $workspace->search('stock_levels'), 'No query: the latest records.');
        self::assertSame([], $workspace->search('stock_levels', 'piano'));
        self::assertSame([], $workspace->search('invoices', 'anything'));
    }

    public function testTheCalendarTellsBusyAndFreeHours(): void
    {
        $workspace = self::workspace();
        $tuesday = new \DateTimeImmutable('2026-11-17');

        self::assertSame(['Operations review', 'Warehouse visit'], array_column($workspace->busy($tuesday), 'what'));
        self::assertSame(['09:00', '11:00', '12:00', '13:00', '16:00', '17:00'], $workspace->free($tuesday));
        self::assertSame([], $workspace->free(new \DateTimeImmutable('2026-11-21')), 'Nobody books a Saturday.');
        self::assertTrue($workspace->overlaps(new \DateTimeImmutable('2026-11-17 10:30'), 30));
        self::assertFalse($workspace->overlaps(new \DateTimeImmutable('2026-11-17 11:00'), 60));
    }

    public function testItGoesBackAndForthAsArrays(): void
    {
        $workspace = self::workspace();
        $again = Workspace::fromArray($workspace->toArray());

        self::assertEquals($workspace, $again);
        self::assertTrue(Workspace::fromArray(null)->isEmpty());
        self::assertFalse($workspace->isEmpty());
        self::assertNull(Workspace::moment('tomorrow at ten'));
        self::assertNull(Workspace::moment('2026-02-30 10:00'), 'No rolling over into March.');
        self::assertTrue(Workspace::isOpen(new \DateTimeImmutable('2026-11-17 17:30'), 30));
        self::assertFalse(Workspace::isOpen(new \DateTimeImmutable('2026-11-17 17:30'), 45));
        self::assertFalse(Workspace::isOpen(new \DateTimeImmutable('2026-11-17 08:30'), 30));
        self::assertFalse(Workspace::isOpen(new \DateTimeImmutable('2026-11-21 10:00'), 30));
    }
}
