<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage;

/**
 * `bin/check-coverage-threshold`: fail when Clover statement and/or line coverage is under a minimum.
 *
 * Usage: check-coverage-threshold <clover.xml> [<min-percent>] [--min=N] [--metric=both|statements|lines]
 *
 * The minimum comes from the second argument, `--min`, or the `COVERAGE_MIN` environment variable.
 * Without a minimum the command only prints the coverage and exits 0 (informational).
 *
 * Exit codes: 0 OK / informational, 1 under threshold or report missing, 2 usage or parse error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageThresholdCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-coverage-threshold <clover.xml> [<min-percent>] [--min=N] [--metric=both|statements|lines]

        Fails (exit 1) when Clover coverage is below the minimum percentage.
        The minimum is the second argument, --min, or the COVERAGE_MIN environment variable;
        without one, coverage is printed and the command exits 0 (informational).
          --metric=both        check statements and lines (default)
          --metric=statements  check statement coverage only (project metrics)
          --metric=lines       check line coverage only (<line type="stmt|cond"> entries)
        Exit codes: 0 OK, 1 below threshold / report missing, 2 usage or parse error.
        TXT;

    private const METRICS = ['both', 'statements', 'lines'];

    /**
     * @param resource              $stdout
     * @param resource              $stderr
     * @param array<string, string> $env    Environment (reads COVERAGE_MIN)
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly array $env = [],
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $positional = [];
        $min = null;
        $metric = 'both';
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (str_starts_with($arg, '--min=')) {
                $min = substr($arg, 6);
            } elseif (str_starts_with($arg, '--metric=')) {
                $metric = substr($arg, 9);
            } elseif (str_starts_with($arg, '--')) {
                return $this->usageError("Unknown option {$arg}");
            } else {
                $positional[] = $arg;
            }
        }

        if ([] === $positional || \count($positional) > 2) {
            return $this->usageError('Expected <clover.xml> and an optional <min-percent>.');
        }
        if (!\in_array($metric, self::METRICS, true)) {
            return $this->usageError("--metric must be one of both, statements, lines (got {$metric})");
        }
        $min ??= $positional[1] ?? null;
        if (null === $min || '' === $min) {
            $min = $this->env['COVERAGE_MIN'] ?? '';
        }
        if ('' !== $min && (!is_numeric($min) || (float) $min < 0 || (float) $min > 100)) {
            fwrite($this->stderr, "ERROR: minimum coverage must be between 0 and 100 (got {$min})\n");

            return 2;
        }

        $clover = $positional[0];

        try {
            $report = CloverReport::fromFile($clover);
        } catch (CloverException $e) {
            fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");
            if (CloverException::NOT_FOUND === $e->getCode()) {
                fwrite($this->stderr, "Generate it with PHPUnit --coverage-clover (e.g. make test-coverage).\n");
            }

            return $e->getCode();
        }

        fwrite($this->stdout, \sprintf(
            "Statement coverage: %s%% (%d/%d) from %s\n",
            $this->format($report->statementPercent()),
            $report->coveredStatements,
            $report->totalStatements,
            $clover,
        ));
        if ($report->hasLines()) {
            fwrite($this->stdout, \sprintf(
                "Line coverage: %s%% (%d/%d) from %s\n",
                $this->format($report->linePercent()),
                $report->coveredLines,
                $report->totalLines,
                $clover,
            ));
        } else {
            fwrite($this->stdout, "Line coverage: n/a (no <line> entries in {$clover})\n");
        }

        if ('' === $min) {
            fwrite($this->stdout, "Threshold: disabled (no minimum given and COVERAGE_MIN unset). Informational only.\n");

            return 0;
        }

        $threshold = (float) $min;
        $checks = [];
        if ('lines' !== $metric) {
            $checks['Statement'] = $report->statementPercent();
        }
        if ('statements' !== $metric) {
            if ($report->hasLines()) {
                $checks['Line'] = $report->linePercent();
            } elseif ('lines' === $metric) {
                fwrite($this->stderr, "ERROR: --metric=lines needs <line> entries in the Clover report.\n");

                return 2;
            }
        }

        $failed = false;
        foreach ($checks as $label => $percent) {
            if ($percent + 1e-9 < $threshold) {
                fwrite($this->stderr, \sprintf(
                    "ERROR: %s coverage %s%% is below threshold %s%%\n",
                    $label,
                    $this->format($percent),
                    $this->format($threshold),
                ));
                $failed = true;
            } else {
                fwrite($this->stdout, \sprintf(
                    "Threshold: OK (%s coverage %s%% >= %s%%)\n",
                    $label,
                    $this->format($percent),
                    $this->format($threshold),
                ));
            }
        }

        return $failed ? 1 : 0;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }

    /** Truncates (never rounds up) so 99.996% is shown as 99.99%, not 100.00%. */
    private function format(float $percent): string
    {
        return \sprintf('%.2f', floor($percent * 100 + 1e-6) / 100);
    }
}
