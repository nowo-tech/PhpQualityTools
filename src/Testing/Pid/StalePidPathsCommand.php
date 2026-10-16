<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Pid;

/**
 * `bin/clean-stale-pid-paths`: removes per-process test files and directories whose process is gone.
 *
 * Usage: clean-stale-pid-paths [--dry-run] [--quiet] <pattern> [<pattern> ...]
 *
 * Each pattern is a path with exactly one `{pid}` placeholder and optional `*` / `?` wildcards
 * (never across `/`), e.g. `/dev/shm/app-phpunit-{pid}.db*` or `/dev/shm/app-markers-{pid}`. Matching
 * paths are removed (directories recursively, symlinks unlinked, never followed) only when the
 * process `{pid}` no longer exists, so a concurrent PHPUnit / ParaTest run keeps its files. Pairs with
 * the `pid_path` env var processor (Testing\Env\PidPathEnvVarProcessor) and with per-process SQLite files.
 *
 * Liveness: `/proc/<pid>` when `/proc` is mounted, else `posix_kill($pid, 0)`; when neither is
 * available nothing is removed.
 *
 * Exit codes: 0 OK (also when nothing matched), 1 a path could not be removed, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class StalePidPathsCommand
{
    public const USAGE = <<<'TXT'
        Usage: clean-stale-pid-paths [--dry-run] [--quiet] <pattern> [<pattern> ...]

        Removes files / directories of processes that no longer exist (per-process test state).
        Each pattern has exactly one {pid} placeholder and optional * / ? wildcards, e.g.
          clean-stale-pid-paths '/dev/shm/app-phpunit-{pid}.db*' '/dev/shm/app-markers-{pid}'
        Paths of running processes are kept. Quote the patterns so the shell does not expand them.
          --dry-run  list what would be removed, remove nothing
          --quiet    print nothing on success
        Exit codes: 0 OK, 1 a path could not be removed, 2 usage error.
        TXT;

    private const PLACEHOLDER = '{pid}';

    /** @var \Closure(int): bool */
    private readonly \Closure $isAlive;

    /**
     * @param resource                   $stdout
     * @param resource                   $stderr
     * @param (callable(int): bool)|null $isAlive Process liveness check (default: /proc, then posix_kill)
     */
    public function __construct(
        private $stdout,
        private $stderr,
        ?callable $isAlive = null,
    ) {
        $this->isAlive = null !== $isAlive ? $isAlive(...) : static fn (int $pid): bool => self::isProcessAlive($pid);
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $dryRun = false;
        $quiet = false;
        $patterns = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if ('--dry-run' === $arg) {
                $dryRun = true;
            } elseif ('--quiet' === $arg || '-q' === $arg) {
                $quiet = true;
            } elseif (str_starts_with($arg, '-')) {
                return $this->usageError("Unknown option {$arg}");
            } else {
                $patterns[] = $arg;
            }
        }
        if ([] === $patterns) {
            return $this->usageError('Expected at least one <pattern>.');
        }
        foreach ($patterns as $pattern) {
            if (1 !== substr_count($pattern, self::PLACEHOLDER)) {
                return $this->usageError("Pattern must contain exactly one {pid} placeholder: {$pattern}");
            }
        }

        $removed = 0;
        $kept = 0;
        $failed = false;
        foreach ($patterns as $pattern) {
            foreach ($this->match($pattern) as $path => $pid) {
                if (($this->isAlive)($pid)) {
                    ++$kept;
                    continue;
                }
                if ($dryRun) {
                    fwrite($this->stdout, "Would remove {$path} (process {$pid} is gone)\n");
                    ++$removed;
                    continue;
                }
                if ($this->remove($path)) {
                    ++$removed;
                } else {
                    fwrite($this->stderr, "ERROR: could not remove {$path}\n");
                    $failed = true;
                }
            }
        }

        if (!$quiet || $dryRun) {
            fwrite($this->stdout, \sprintf(
                "clean-stale-pid-paths: %s %d path(s) of dead processes, kept %d of running processes.\n",
                $dryRun ? 'would remove' : 'removed',
                $removed,
                $kept,
            ));
        }

        return $failed ? 1 : 0;
    }

    /**
     * @return array<string, int> path => pid
     */
    private function match(string $pattern): array
    {
        [$before, $after] = explode(self::PLACEHOLDER, $pattern, 2);
        $regex = '#^' . $this->globToRegex($before) . '([0-9]+)' . $this->globToRegex($after) . '$#';
        $glob = glob($this->escapeGlob($before) . '[0-9]*' . $this->escapeGlob($after), \GLOB_NOSORT) ?: [];

        $matches = [];
        foreach ($glob as $path) {
            if (1 === preg_match($regex, $path, $m) && (int) $m[1] > 0) {
                $matches[$path] = (int) $m[1];
            }
        }
        ksort($matches);

        return $matches;
    }

    /** Keeps `*` / `?` as wildcards, escapes the other glob metacharacters (`[`, `]`, `{`, `}`, `\`). */
    private function escapeGlob(string $part): string
    {
        return (string) preg_replace('/([\[\]{}\\\\])/', '\\\\$1', $part);
    }

    private function globToRegex(string $part): string
    {
        return strtr(preg_quote($part, '#'), ['\*' => '[^/]*', '\?' => '[^/]']);
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }

    private function remove(string $path): bool
    {
        if (is_link($path) || !is_dir($path)) {
            return @unlink($path);
        }
        $entries = @scandir($path) ?: [];
        $ok = true;
        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $ok = $this->remove($path . '/' . $entry) && $ok;
        }

        return $ok && @rmdir($path);
    }

    /**
     * Default liveness check: `<proc>/<pid>` when procfs is mounted, else `posix_kill($pid, 0)`; when
     * neither is available the process is assumed alive (nothing is removed).
     */
    public static function isProcessAlive(int $pid, string $proc = '/proc', bool $usePosix = true): bool
    {
        if (is_dir($proc . '/self')) {
            return is_dir($proc . '/' . $pid);
        }
        if ($usePosix && \function_exists('posix_kill')) {
            // EPERM (1): the process exists but belongs to another user.
            return posix_kill($pid, 0) || 1 === posix_get_last_error();
        }

        return true;
    }
}
