<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\DemoLock;

/**
 * `bin/check-demo-lock`: does each `demo/<name>/composer.lock` satisfy the bundle's own `composer.json` `require`?
 *
 * A demo installs the bundle from a path repository with `composer install`, i.e. from its lock. The lock stores the
 * bundle's requirements as they were when the lock was last updated, so a requirement added or raised later is not
 * installed and the demo fails with "class not found". The gate reports, for every requirement of the bundle, the
 * version the demo lock resolves and whether it satisfies the constraint.
 *
 * A demo without a `composer.lock` (git-ignored locks, resolved at install) is reported and passes.
 *
 * Exit codes: 0 OK, 1 stale lock, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class DemoLockCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-demo-lock [<bundle-dir>] [--only=<glob>]...

        Fails when a demo/<name>/composer.lock does not satisfy the requirements of the bundle's composer.json
        (a requirement added or raised after the lock was last updated is not installed in the demo).
          <bundle-dir>    directory of the bundle (default: current directory)
          --only=<glob>   check only the requirements whose name matches (fnmatch), repeatable; "--only <glob>" works too
                          (default: every vendor/package requirement)
        A demo without a composer.lock is reported and passes. Platform requirements (php, ext-*) are not checked.
        Exit codes: 0 OK, 1 stale demo lock, 2 usage error.
        TXT;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $bundle = null;
        $only = [];
        for ($i = 0; $i < \count($args); ++$i) {
            $arg = $args[$i];
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if ('--only' === $arg) {
                if (!isset($args[$i + 1])) {
                    return $this->usageError('--only needs a pattern');
                }
                $only[] = $args[++$i];
            } elseif (str_starts_with($arg, '--only=')) {
                $only[] = substr($arg, 7);
            } elseif (str_starts_with($arg, '-')) {
                return $this->usageError("Unknown option {$arg}");
            } elseif (null === $bundle) {
                $bundle = $arg;
            } else {
                return $this->usageError('Only one bundle directory is accepted');
            }
        }
        $only = [] === $only ? ['*'] : $only;

        $bundle = rtrim(null === $bundle ? $this->cwd : (str_starts_with($bundle, '/') ? $bundle : $this->cwd . '/' . $bundle), '/');
        $manifest = $bundle . '/composer.json';
        if (!is_file($manifest)) {
            fwrite($this->stderr, "ERROR: composer.json not found in {$bundle}\n");

            return 2;
        }
        $require = $this->decode($manifest)['require'] ?? null;
        if (!\is_array($require)) {
            $require = [];
        }
        $requirements = [];
        foreach ($require as $name => $constraint) {
            if (\is_string($constraint) && str_contains((string) $name, '/') && $this->matches((string) $name, $only)) {
                $requirements[(string) $name] = $constraint;
            }
        }

        $stale = false;
        $checked = 0;
        $manifests = glob($bundle . '/demo/*/composer.json') ?: [];
        sort($manifests);
        foreach ($manifests as $demoManifest) {
            $lock = \dirname($demoManifest) . '/composer.lock';
            $relative = substr($lock, \strlen($bundle) + 1);
            if (!is_file($lock)) {
                fwrite($this->stdout, 'OK ' . substr($demoManifest, \strlen($bundle) + 1) . ": no composer.lock (resolved at install)\n");
                continue;
            }
            ++$checked;
            $installed = [];
            $packages = $this->decode($lock)['packages'] ?? null;
            foreach (\is_array($packages) ? $packages : [] as $package) {
                if (\is_array($package) && \is_string($package['name'] ?? null) && \is_string($package['version'] ?? null)) {
                    $installed[$package['name']] = $package['version'];
                }
            }
            foreach ($requirements as $name => $constraint) {
                $got = $installed[$name] ?? null;
                if (null === $got || !ConstraintMatcher::satisfies($got, $constraint)) {
                    $stale = true;
                    fwrite($this->stderr, "STALE {$relative}: {$name} {$constraint} -> " . ($got ?? 'not installed') . "\n");
                }
            }
        }

        if ($stale) {
            return 1;
        }
        fwrite($this->stdout, \sprintf("OK: %d demo lock(s) satisfy %d requirement(s).\n", $checked, \count($requirements)));

        return 0;
    }

    /**
     * @param list<string> $patterns
     */
    private function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);

        /* @var array<string, mixed> */
        return \is_array($data) ? $data : [];
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
