<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps every record in memory, for assertions on what a service logged (and on what it did not log:
 * {@see self::dump()} and {@see self::contextValues()} serialise every message and context value).
 *
 * Needs `psr/log`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // @igor-ignore - PHPUnit test-support code for the consumers' test suites, never a service of a worker application.
        $this->records[] = [
            'level' => \is_string($level) || $level instanceof \Stringable ? (string) $level : get_debug_type($level),
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * The record messages in order.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(static fn (array $record): string => $record['message'], $this->records);
    }

    /**
     * The record levels in order.
     *
     * @return list<string>
     */
    public function levels(): array
    {
        return array_map(static fn (array $record): string => $record['level'], $this->records);
    }

    /**
     * "level: message" of every record.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (array $record): string => $record['level'] . ': ' . $record['message'], $this->records);
    }

    /**
     * The records of one level.
     *
     * @return list<array{level: string, message: string, context: array<array-key, mixed>}>
     */
    public function recordsWithLevel(string $level): array
    {
        return array_values(array_filter($this->records, static fn (array $record): bool => $record['level'] === $level));
    }

    /**
     * Whether a message contains the fragment.
     */
    public function has(string $fragment): bool
    {
        foreach ($this->records as $record) {
            if (str_contains($record['message'], $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every scalar context value of every record, for "no personal data / no secret" assertions. Objects that are not
     * stringable (an enum parameter type of a Doctrine query log, say) appear as their type.
     *
     * @return list<string>
     */
    public function contextValues(): array
    {
        $values = [];
        foreach ($this->records as $record) {
            array_walk_recursive($record['context'], static function (mixed $value) use (&$values): void {
                $values[] = \is_scalar($value) || null === $value || $value instanceof \Stringable ? (string) $value : get_debug_type($value);
            });
        }

        return $values;
    }

    /**
     * Every record (level, message, context) as one JSON string, so a test can assert that a secret never appears.
     * Values that JSON cannot encode (resources, invalid UTF-8) are replaced.
     */
    public function dump(): string
    {
        return (string) json_encode($this->records, \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Forgets the records.
     */
    public function clear(): void
    {
        $this->records = [];
    }
}
