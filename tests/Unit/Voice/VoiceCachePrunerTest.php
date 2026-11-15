<?php

declare(strict_types=1);

namespace App\Tests\Unit\Voice;

use App\Voice\VoiceCachePruner;
use PHPUnit\Framework\TestCase;

/**
 * The voice cache keeps the lines heard recently and drops the others, and synthesis leftovers.
 */
final class VoiceCachePrunerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/decroche-voices-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testOldLinesAndLeftoversGo(): void
    {
        $now = new \DateTimeImmutable('2026-11-13 12:00:00');
        $this->file('recent.mp3', $now->modify('-2 days'));
        $this->file('old.mp3', $now->modify('-45 days'));
        $this->file('old.wav', $now->modify('-45 days'));
        $this->file('old.mp3.ab12cd.part', $now->modify('-2 hours'));
        $this->file('fresh.mp3.ab12cd.part', $now->modify('-5 minutes'));
        $this->file('notes.txt', $now->modify('-90 days'));

        $deleted = new VoiceCachePruner($this->dir)->prune(30, $now);

        self::assertSame(3, $deleted['files']);
        self::assertSame(['fresh.mp3.ab12cd.part', 'notes.txt', 'recent.mp3'], array_map(basename(...), glob($this->dir.'/*') ?: []));
    }

    public function testAMissingDirectory(): void
    {
        self::assertSame(['files' => 0, 'bytes' => 0], new VoiceCachePruner($this->dir.'/missing')->prune());
    }

    private function file(string $name, \DateTimeImmutable $at): void
    {
        file_put_contents($this->dir.'/'.$name, 'x');
        touch($this->dir.'/'.$name, $at->getTimestamp());
    }
}
