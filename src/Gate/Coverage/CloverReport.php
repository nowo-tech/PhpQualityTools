<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage;

use function count;

/**
 * Statement and line totals read from a Clover XML report (PHPUnit `--coverage-clover`).
 *
 * Statements come from `project/metrics` (`coveredstatements` / `statements`). Lines are the
 * distinct `<line type="stmt|cond">` entries (per file and line number) with `count > 0` covered.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class CloverReport
{
    public function __construct(
        public int $coveredStatements,
        public int $totalStatements,
        public int $coveredLines,
        public int $totalLines,
    ) {
    }

    /**
     * @throws CloverException when the file is missing, not XML, or has no project metrics
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new CloverException("Clover report not found at {$path}", CloverException::NOT_FOUND);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml instanceof \SimpleXMLElement) {
            throw new CloverException("Unable to parse Clover XML: {$path}", CloverException::INVALID);
        }

        $metrics = $xml->project->metrics;
        if (0 === \count($metrics)) {
            throw new CloverException("Missing project/metrics in Clover XML: {$path}", CloverException::INVALID);
        }

        $lines = [];
        foreach ($xml->xpath('//file') ?: [] as $file) {
            $name = (string) $file['name'];
            foreach ($file->line as $line) {
                $type = (string) $line['type'];
                if ('stmt' !== $type && 'cond' !== $type) {
                    continue;
                }
                $key = $name . ':' . (int) $line['num'];
                $lines[$key] = ($lines[$key] ?? false) || (int) $line['count'] > 0;
            }
        }

        return new self(
            (int) $metrics['coveredstatements'],
            (int) $metrics['statements'],
            \count(array_filter($lines)),
            \count($lines),
        );
    }

    public function statementPercent(): float
    {
        return $this->percent($this->coveredStatements, $this->totalStatements);
    }

    public function linePercent(): float
    {
        return $this->percent($this->coveredLines, $this->totalLines);
    }

    public function hasLines(): bool
    {
        return $this->totalLines > 0;
    }

    private function percent(int $covered, int $total): float
    {
        return $total > 0 ? 100.0 * $covered / $total : 0.0;
    }
}
