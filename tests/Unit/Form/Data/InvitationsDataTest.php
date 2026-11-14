<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\Data;

use App\Form\Data\InvitationsData;
use PHPUnit\Framework\TestCase;

/**
 * The addresses to invite: split on blanks, commas and semicolons, invalid ones dropped, duplicates removed.
 */
final class InvitationsDataTest extends TestCase
{
    public function testAddresses(): void
    {
        $data = new InvitationsData();
        $data->emails = "alice@example.com, bob@example.com;carol@example.com\n\n not-an-email\tdave@example.org alice@example.com";

        self::assertSame(['alice@example.com', 'bob@example.com', 'carol@example.com', 'dave@example.org'], $data->addresses());
    }

    public function testNoAddress(): void
    {
        $data = new InvitationsData();
        self::assertSame([], $data->addresses());

        $data->emails = ' , ; nope @ ';
        self::assertSame([], $data->addresses());
    }

    public function testAtMostAHundred(): void
    {
        $data = new InvitationsData();
        $data->emails = implode(' ', array_map(static fn (int $i): string => "user{$i}@example.com", range(1, 150)));

        self::assertCount(100, $data->addresses());
    }
}
