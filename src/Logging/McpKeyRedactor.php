<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Keeps the members' personal MCP keys out of the logs: an MCP client may pass its key in the address (/mcp?key=…),
 * which the router logs with every request it matches.
 */
#[AsMonologProcessor]
final readonly class McpKeyRedactor implements ProcessorInterface
{
    private const string KEY = '/([?&]key=)[^&#\s"\']+/';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: self::redact($record->message), context: self::redactAll($record->context));
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private static function redactAll(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            \is_string($value) => self::redact($value),
            \is_array($value) => self::redactAll($value),
            default => $value,
        }, $values);
    }

    private static function redact(string $text): string
    {
        return str_contains($text, 'key=') ? (string) preg_replace(self::KEY, '$1[redacted]', $text) : $text;
    }
}
