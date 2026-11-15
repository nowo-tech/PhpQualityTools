<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * A `--coverage-php` file read by {@see CoverageFormat}: the per-test data, what produced it and the library's own
 * object (kept to write the merged report in the same format).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class LoadedCoverage
{
    /**
     * @param object|array<string, mixed> $native        the CodeCoverage object (php-code-coverage up to 11) or the
     *                                                   serialized payload array (later versions)
     * @param string                      $driver        coverage driver (and its version) that collected the data
     * @param bool                        $hasBranchData branch / path coverage present (a resume does not merge it)
     * @param bool                        $countsHits    the library counts hits per test (index => hits format)
     */
    public function __construct(
        public PerTestCoverage $coverage,
        public object|array $native,
        public string $driver,
        public bool $hasBranchData,
        public bool $countsHits,
    ) {
    }
}
