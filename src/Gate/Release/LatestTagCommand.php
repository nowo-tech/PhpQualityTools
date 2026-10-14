<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Release;

use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;
use NowoTech\PhpQualityTools\Process\ExecCommandRunner;

/**
 * `bin/assert-latest-tag`: succeed only when a tag is the highest semver release tag of the repository.
 *
 * Guards tag-triggered deploys: pushing (or backfilling) an older tag such as `v1.51.0` after `v1.60.0`
 * must never roll production back. Release tags are `<prefix>MAJOR.MINOR.PATCH` (default prefix `v`);
 * pre-releases (`v2.0.0-rc1`) and other tags are never "latest".
 *
 * Usage: assert-latest-tag [<tag>] [--prefix=v] [--stdin] [--fetch[=<remote>]] [--github-output]
 *
 * Exit codes: 0 latest (or decision written with --github-output), 1 not the latest, 2 usage / git error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LatestTagCommand
{
    public const USAGE = <<<'TXT'
        Usage: assert-latest-tag [<tag>] [--prefix=v] [--stdin] [--fetch[=<remote>]] [--github-output]

        Checks that <tag> is the highest semver release tag (<prefix>MAJOR.MINOR.PATCH).
        <tag> defaults to GITHUB_REF_NAME (a leading refs/tags/ is stripped).
          --prefix=v          release tag prefix (default v; --prefix= for bare 1.2.3 tags)
          --stdin             read the tag list from stdin (one per line) instead of `git tag --list`
          --fetch[=<remote>]  run `git fetch --tags --force --quiet <remote>` first (default origin)
          --github-output     append deploy=true|false and latest=<tag> to $GITHUB_OUTPUT and exit 0
                              (a ::notice annotation explains a skipped deploy)
        Exit codes: 0 latest (or decision written with --github-output), 1 not the latest,
                    2 usage error, git error or $GITHUB_OUTPUT not writable.
        TXT;

    private readonly CommandRunnerInterface $runner;

    /**
     * @param resource              $stdout
     * @param resource              $stderr
     * @param array<string, string> $env    Environment (reads GITHUB_REF_NAME, GITHUB_OUTPUT)
     * @param resource|null         $stdin  Tag list source for --stdin
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly array $env = [],
        ?CommandRunnerInterface $runner = null,
        private $stdin = null,
    ) {
        $this->runner = $runner ?? new ExecCommandRunner();
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $positional = [];
        $prefix = 'v';
        $fromStdin = false;
        $fetchRemote = null;
        $githubOutput = false;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (str_starts_with($arg, '--prefix=')) {
                $prefix = substr($arg, 9);
            } elseif ('--stdin' === $arg) {
                $fromStdin = true;
            } elseif ('--fetch' === $arg) {
                $fetchRemote = 'origin';
            } elseif (str_starts_with($arg, '--fetch=')) {
                $fetchRemote = substr($arg, 8);
            } elseif ('--github-output' === $arg) {
                $githubOutput = true;
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
        if ('' === $tag) {
            return $this->usageError('No tag given and GITHUB_REF_NAME is unset.');
        }
        if (1 !== preg_match('/^[A-Za-z0-9._\/-]*$/', $prefix)) {
            return $this->usageError("Invalid --prefix {$prefix}");
        }
        if ('' === $fetchRemote) {
            return $this->usageError('--fetch= needs a remote name.');
        }
        if ($fromStdin && null !== $fetchRemote) {
            return $this->usageError('--fetch cannot be combined with --stdin.');
        }
        $outputFile = $this->env['GITHUB_OUTPUT'] ?? '';
        if ($githubOutput && '' === $outputFile) {
            return $this->usageError('--github-output needs the GITHUB_OUTPUT environment variable.');
        }

        $tags = $fromStdin ? $this->readStdin() : $this->listGitTags($prefix, $fetchRemote);
        if (null === $tags) {
            return 2;
        }

        $latest = self::latest($tags, $prefix);
        $isLatest = null !== $latest && $latest === $tag;
        fwrite($this->stdout, \sprintf("pushed=%s latest=%s\n", $tag, $latest ?? '<none>'));

        if ($isLatest) {
            fwrite($this->stdout, "OK: {$tag} is the latest release tag.\n");
        } else {
            $reason = null === self::parse($tag, $prefix)
                ? \sprintf('%s is not a release tag (%sMAJOR.MINOR.PATCH)', $tag, $prefix)
                : \sprintf('%s is not the latest release (%s)', $tag, $latest ?? 'none');
            if ($githubOutput) {
                fwrite($this->stdout, "::notice title=Deploy skipped::{$reason}, skipping deploy\n");
            } else {
                fwrite($this->stderr, "ERROR: {$reason}.\n");
            }
        }

        if (!$githubOutput) {
            return $isLatest ? 0 : 1;
        }

        $lines = \sprintf("deploy=%s\nlatest=%s\n", $isLatest ? 'true' : 'false', $latest ?? '');
        if (is_dir($outputFile) || false === @file_put_contents($outputFile, $lines, \FILE_APPEND)) {
            fwrite($this->stderr, "ERROR: cannot write to GITHUB_OUTPUT ({$outputFile}).\n");

            return 2;
        }

        return 0;
    }

    /**
     * Highest release tag of a list, or null when none matches `<prefix>MAJOR.MINOR.PATCH`.
     *
     * @param list<string> $tags
     */
    public static function latest(array $tags, string $prefix = 'v'): ?string
    {
        $latest = null;
        $latestVersion = null;
        foreach ($tags as $tag) {
            $version = self::parse(trim($tag), $prefix);
            if (null !== $version && (null === $latestVersion || $version > $latestVersion)) {
                $latest = trim($tag);
                $latestVersion = $version;
            }
        }

        return $latest;
    }

    /**
     * @return array{int, int, int}|null [major, minor, patch]
     */
    private static function parse(string $tag, string $prefix): ?array
    {
        if (1 !== preg_match('/^' . preg_quote($prefix, '/') . '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $tag, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    /**
     * @return list<string>|null null after reporting a git error
     */
    private function listGitTags(string $prefix, ?string $fetchRemote): ?array
    {
        if (null !== $fetchRemote) {
            $fetch = $this->runner->run('git fetch --tags --force --quiet ' . escapeshellarg($fetchRemote));
            if (0 !== $fetch['exitCode']) {
                return $this->gitError('git fetch --tags', $fetch['output']);
            }
        }

        $list = $this->runner->run('git tag --list ' . escapeshellarg($prefix . '*'));
        if (0 !== $list['exitCode']) {
            return $this->gitError('git tag --list', $list['output']);
        }

        return $list['output'];
    }

    /**
     * @return list<string>
     */
    private function readStdin(): array
    {
        $content = \is_resource($this->stdin) ? (string) stream_get_contents($this->stdin) : '';

        return preg_split('/\R/', $content, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param list<string> $output
     */
    private function gitError(string $command, array $output): null
    {
        fwrite($this->stderr, "ERROR: {$command} failed (run inside a git checkout with tags, e.g. actions/checkout fetch-depth: 0).\n");
        if ([] !== $output) {
            fwrite($this->stderr, implode("\n", $output) . "\n");
        }

        return null;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
