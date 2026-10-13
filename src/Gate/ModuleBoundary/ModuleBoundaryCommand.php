<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * `bin/check-module-boundaries`: fail when the project breaks its module-boundary map.
 *
 * Usage: check-module-boundaries [--config=<file>] [--root=<dir>]
 *
 * Default config: `config/quality/module-boundaries.yaml` (then `.yml`, then `.php`) under the root;
 * the root defaults to the current directory. Paths in the config are relative to the root.
 *
 * Exit codes: 0 OK, 1 violations, 2 usage / configuration error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryCommand
{
    public const DEFAULT_CONFIGS = [
        'config/quality/module-boundaries.yaml',
        'config/quality/module-boundaries.yml',
        'config/quality/module-boundaries.php',
    ];

    public const USAGE = <<<'TXT'
        Usage: check-module-boundaries [--config=<file>] [--root=<dir>]

        Checks forbidden imports between modules and the extra rules of a module-boundary map.
          --config=<file>  YAML (needs symfony/yaml) or PHP file returning an array
                           (default: config/quality/module-boundaries.{yaml,yml,php} under the root)
          --root=<dir>     project root the config paths are relative to (default: current directory)
        Exit codes: 0 OK, 1 violations, 2 usage or configuration error.
        TXT;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
        private readonly ModuleBoundaryChecker $checker = new ModuleBoundaryChecker(),
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $config = null;
        $root = $this->cwd;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                \fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (\str_starts_with($arg, '--config=')) {
                $config = \substr($arg, 9);
            } elseif (\str_starts_with($arg, '--root=')) {
                $root = $this->resolve(\substr($arg, 7), $this->cwd);
            } else {
                \fwrite($this->stderr, "ERROR: Unknown argument {$arg}\n" . self::USAGE . "\n");

                return 2;
            }
        }

        if (!\is_dir($root)) {
            \fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }

        if (null === $config) {
            foreach (self::DEFAULT_CONFIGS as $candidate) {
                if (\is_file($root . '/' . $candidate)) {
                    $config = $root . '/' . $candidate;
                    break;
                }
            }
            if (null === $config) {
                \fwrite($this->stderr, "ERROR: no config found (looked for config/quality/module-boundaries.{yaml,yml,php} in {$root}); pass --config=<file>.\n");

                return 2;
            }
        } else {
            $config = $this->resolve($config, $this->cwd);
        }

        try {
            $loaded = ModuleBoundaryConfig::fromFile($config);
        } catch (ConfigException $e) {
            \fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

            return 2;
        }

        $result = $this->checker->check($loaded, $root);
        foreach ($result['violations'] as $violation) {
            \fwrite($this->stderr, 'error: ' . $violation->message . "\n");
            foreach ($violation->details as $detail) {
                \fwrite($this->stderr, '  ' . $detail . "\n");
            }
        }

        if ([] !== $result['violations']) {
            \fwrite($this->stderr, \sprintf("Module boundaries: %d violation(s).\n", \count($result['violations'])));

            return 1;
        }

        \fwrite($this->stdout, \sprintf(
            "OK: module boundaries (%d module(s), %d PHP file(s) scanned, %d edge rule(s), %d extra rule(s)).\n",
            \count($loaded->modules),
            $result['files'],
            \count($loaded->allowedEdges) + \count($loaded->forbiddenEdges),
            \count($loaded->rules),
        ));

        return 0;
    }

    private function resolve(string $path, string $base): string
    {
        if (\str_starts_with($path, '/') || \str_contains($path, ':')) {
            return \rtrim($path, '/');
        }

        return \rtrim($base, '/') . '/' . \rtrim($path, '/');
    }
}
