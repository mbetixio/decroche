<?php

declare(strict_types=1);

namespace App\Voice;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the cache of synthesized lines from growing forever: a line not heard for a while (its file is touched each
 * time it is reused) is deleted, as are synthesis leftovers. Run by "app:prune-voices", at each container start, and
 * now and then after a synthesis.
 */
final readonly class VoiceCachePruner
{
    /** By default, lines not heard for this many days go. */
    public const int DAYS = 30;

    public function __construct(
        #[Autowire('%env(default:voices_dir:VOICES_DIR)%')]
        private string $cache,
    ) {
    }

    /**
     * @return array{files: int, bytes: int} what was deleted
     */
    public function prune(int $days = self::DAYS, ?\DateTimeImmutable $now = null): array
    {
        $deleted = ['files' => 0, 'bytes' => 0];
        if (!is_dir($this->cache)) {
            return $deleted;
        }
        $limit = ($now ?? new \DateTimeImmutable())->getTimestamp() - $days * 86400;
        // Unfinished syntheses (".part") are never reused: an hour is enough.
        $partLimit = ($now ?? new \DateTimeImmutable())->getTimestamp() - 3600;
        foreach (new \DirectoryIterator($this->cache) as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $stale = match (true) {
                str_ends_with($file->getFilename(), '.part') => $file->getMTime() < $partLimit,
                str_ends_with($file->getFilename(), '.mp3') => $file->getMTime() < $limit,
                default => false,
            };
            if ($stale) {
                $size = $file->getSize();
                if (@unlink($file->getPathname())) {
                    ++$deleted['files'];
                    $deleted['bytes'] += $size;
                }
            }
        }

        return $deleted;
    }
}
