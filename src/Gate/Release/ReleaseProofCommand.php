<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Release;

use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;
use NowoTech\PhpQualityTools\Process\ExecCommandRunner;

/**
 * `bin/assert-release-proof`: succeed only when an annotated release tag records a passing local gate
 * for its own commit — a line `release-check: OK <full commit sha>` in the tag message, written right
 * after a green `make release-check` (`git tag -a vX.Y.Z -m "Release vX.Y.Z" -m "release-check: OK $(git rev-parse HEAD)"`).
 *
 * Tag-triggered deploys use it as the gate while hosted CI cannot run (billing, outage); with green CI the
 * workflow may skip it. An explicit override (`--allow-skip` with `RELEASE_GATE=skip`) is logged.
 *
 * Usage: assert-release-proof [<tag>] [--github-output] [--allow-skip]
 *
 * Exit codes: 0 proof found (or decision written with --github-output, or logged skip), 1 no proof, 2 usage / git error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ReleaseProofCommand
{
    public const USAGE = <<<'TXT'
        Usage: assert-release-proof [<tag>] [--github-output] [--allow-skip]

        Checks that the annotated <tag> message contains "release-check: OK <sha>" where <sha> is the
        full commit the tag points to. <tag> defaults to GITHUB_REF_NAME (refs/tags/ is stripped).
          --github-output  append proof=true|false to $GITHUB_OUTPUT and exit 0
          --allow-skip     accept RELEASE_GATE=skip from the environment (prints a warning; for emergencies)
        Exit codes: 0 proof found / decision written / logged skip, 1 no proof, 2 usage or git error.
        TXT;

    public const PROOF_PREFIX = 'release-check: OK ';

    private readonly CommandRunnerInterface $runner;

    /**
     * @param resource              $stdout
     * @param resource              $stderr
     * @param array<string, string> $env    Environment (reads GITHUB_REF_NAME, GITHUB_OUTPUT, RELEASE_GATE)
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly array $env = [],
        ?CommandRunnerInterface $runner = null,
    ) {
        $this->runner = $runner ?? new ExecCommandRunner();
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $positional = [];
        $githubOutput = false;
        $allowSkip = false;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if ('--github-output' === $arg) {
                $githubOutput = true;
            } elseif ('--allow-skip' === $arg) {
                $allowSkip = true;
            } elseif (str_starts_with($arg, '-')) {
                return $this->usageError("Unknown option {$arg}");
            } else {
                $positional[] = $arg;
            }
        }
        if (\count($positional) > 1) {
            return $this->usageError('Expected at most one <tag>.');
        }
        $tag = $positional[0] ?? ($this->env['GITHUB_REF_NAME'] ?? '');
        if (str_starts_with($tag, 'refs/tags/')) {
            $tag = substr($tag, 10);
        }
        if ('' === $tag || 1 !== preg_match('/^[A-Za-z0-9._\/-]+$/', $tag)) {
            return $this->usageError('No valid tag given and GITHUB_REF_NAME is unset.');
        }
        $outputFile = $this->env['GITHUB_OUTPUT'] ?? '';
        if ($githubOutput && '' === $outputFile) {
            return $this->usageError('--github-output needs the GITHUB_OUTPUT environment variable.');
        }

        if ($allowSkip && 'skip' === ($this->env['RELEASE_GATE'] ?? '')) {
            fwrite($this->stdout, "::warning title=Release gate skipped::{$tag} deployed without release-check proof (RELEASE_GATE=skip)\n");

            return $this->decide($githubOutput, $outputFile, true);
        }

        $sha = $this->runner->run('git rev-list -n 1 ' . escapeshellarg('refs/tags/' . $tag));
        $message = $this->runner->run('git for-each-ref ' . escapeshellarg('refs/tags/' . $tag) . ' ' . escapeshellarg('--format=%(contents)'));
        if (0 !== $sha['exitCode'] || 0 !== $message['exitCode'] || [] === $sha['output']) {
            fwrite($this->stderr, "ERROR: cannot read tag {$tag} (run inside a git checkout with tags).\n");

            return 2;
        }

        $commit = trim($sha['output'][0]);
        $hasProof = \in_array(self::PROOF_PREFIX . $commit, array_map(trim(...), $message['output']), true);
        if ($hasProof) {
            fwrite($this->stdout, "OK: {$tag} records a passing release-check for {$commit}.\n");
        } else {
            $reason = \sprintf('%s has no "%s%s" line (lightweight tag, or proof for another commit)', $tag, self::PROOF_PREFIX, $commit);
            fwrite($githubOutput ? $this->stdout : $this->stderr, $githubOutput ? "::error title=No release-check proof::{$reason}\n" : "ERROR: {$reason}.\n");
        }

        return $this->decide($githubOutput, $outputFile, $hasProof);
    }

    private function decide(bool $githubOutput, string $outputFile, bool $ok): int
    {
        if (!$githubOutput) {
            return $ok ? 0 : 1;
        }
        if (is_dir($outputFile) || false === @file_put_contents($outputFile, 'proof=' . ($ok ? 'true' : 'false') . "\n", \FILE_APPEND)) {
            fwrite($this->stderr, "ERROR: cannot write to GITHUB_OUTPUT ({$outputFile}).\n");

            return 2;
        }

        return 0;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
