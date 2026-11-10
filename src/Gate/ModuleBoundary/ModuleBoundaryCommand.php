<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * `bin/check-module-boundaries`: fail when the project breaks its module-boundary map.
 *
 * Usage: check-module-boundaries [--config=<file>] [--root=<dir>]
 *        [--baseline=<file>] [--strict-baseline] [--update-baseline [--allow-grow]]   (since 1.12.0, `layers` maps)
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
        Layer rules (config with a "layers" section):
          --baseline=<file>   accepted layer findings (default: layers.baseline under the root)
          --strict-baseline   fail when a baseline entry no longer occurs (default: reported only)
          --update-baseline   rewrite the baseline (drops fixed findings) and exit; on an existing baseline
                              new findings are listed but only added with --allow-grow
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
        private readonly LayerChecker $layerChecker = new LayerChecker(),
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $config = null;
        $root = $this->cwd;
        $baseline = null;
        $flags = ['--strict-baseline' => false, '--update-baseline' => false, '--allow-grow' => false];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (str_starts_with($arg, '--config=')) {
                $config = substr($arg, 9);
            } elseif (str_starts_with($arg, '--root=')) {
                $root = $this->resolve(substr($arg, 7), $this->cwd);
            } elseif (str_starts_with($arg, '--baseline=') && '' !== substr($arg, 11)) {
                $baseline = $this->resolve(substr($arg, 11), $this->cwd);
            } elseif (isset($flags[$arg])) {
                $flags[$arg] = true;
            } else {
                fwrite($this->stderr, "ERROR: Unknown argument {$arg}\n" . self::USAGE . "\n");

                return 2;
            }
        }

        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }

        if (null === $config) {
            foreach (self::DEFAULT_CONFIGS as $candidate) {
                if (is_file($root . '/' . $candidate)) {
                    $config = $root . '/' . $candidate;
                    break;
                }
            }
            if (null === $config) {
                fwrite($this->stderr, "ERROR: no config found (looked for config/quality/module-boundaries.{yaml,yml,php} in {$root}); pass --config=<file>.\n");

                return 2;
            }
        } else {
            $config = $this->resolve($config, $this->cwd);
        }

        try {
            $loaded = ModuleBoundaryConfig::fromFile($config);
        } catch (ConfigException $e) {
            fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

            return 2;
        }

        $layered = null !== $baseline || \in_array(true, $flags, true);
        if ($layered && !$loaded->layers instanceof LayerConfig) {
            fwrite($this->stderr, "ERROR: --baseline, --strict-baseline, --update-baseline and --allow-grow need a \"layers\" section in {$config}.\n");

            return 2;
        }
        if ($flags['--allow-grow'] && !$flags['--update-baseline']) {
            fwrite($this->stderr, "ERROR: --allow-grow only applies to --update-baseline.\n");

            return 2;
        }
        $accepted = [];
        if ($loaded->layers instanceof LayerConfig) {
            $baseline ??= $this->resolve($loaded->layers->baseline, $root);

            try {
                $accepted = LayerBaseline::load($baseline);
            } catch (ConfigException $e) {
                fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

                return 2;
            }
            if ($flags['--update-baseline']) {
                return $this->updateBaseline($loaded, $root, $baseline, $accepted, $flags['--allow-grow']);
            }
        }

        $result = $this->checker->check($loaded, $root);
        foreach ($result['violations'] as $violation) {
            fwrite($this->stderr, 'error: ' . $violation->message . "\n");
            foreach ($violation->details as $detail) {
                fwrite($this->stderr, '  ' . $detail . "\n");
            }
        }

        $errors = \count($result['violations']);
        if ($loaded->layers instanceof LayerConfig) {
            $errors += $this->reportLayers($loaded, $loaded->layers, $root, $accepted, $flags['--strict-baseline']);
        }

        if ($errors > 0) {
            fwrite($this->stderr, \sprintf("Module boundaries: %d violation(s).\n", $errors));

            return 1;
        }

        fwrite($this->stdout, \sprintf(
            "OK: module boundaries (%d module(s), %d PHP file(s) scanned, %d edge rule(s), %d extra rule(s)).\n",
            \count($loaded->modules),
            $result['files'],
            \count($loaded->allowedEdges) + \count($loaded->forbiddenEdges),
            \count($loaded->rules),
        ));

        return 0;
    }

    /**
     * Prints the layer findings (errors for enforce contexts, warnings for warn contexts, baselined ones counted) and
     * the stale baseline entries; returns the number of failures.
     *
     * @param array<string, int> $accepted
     */
    private function reportLayers(ModuleBoundaryConfig $config, LayerConfig $layers, string $root, array $accepted, bool $strict): int
    {
        $result = $this->layerChecker->check($config, $root);
        $applied = LayerBaseline::apply($accepted, $result['violations']);
        $errors = 0;
        $warnings = 0;
        foreach ($applied['new'] as $violation) {
            if ('enforce' === $violation->mode) {
                fwrite($this->stderr, 'error: ' . $violation->describe() . "\n");
                ++$errors;
            } else {
                fwrite($this->stdout, 'warning: ' . $violation->describe() . "\n");
                ++$warnings;
            }
        }
        foreach ($applied['stale'] as $key) {
            $line = 'baseline entry no longer occurs (remove it, or run --update-baseline): ' . $key . "\n";
            if ($strict) {
                fwrite($this->stderr, 'error: ' . $line);
                ++$errors;
            } else {
                fwrite($this->stdout, 'warning: ' . $line);
            }
        }
        fwrite($this->stdout, \sprintf(
            "Layers: %d context(s), %d file(s), %d rule(s): %d error(s), %d warning(s), %d baselined, %d stale baseline entr%s.\n",
            \count($layers->contexts),
            $result['files'],
            \count($layers->rules),
            $errors,
            $warnings,
            $applied['baselined'],
            \count($applied['stale']),
            1 === \count($applied['stale']) ? 'y' : 'ies',
        ));

        return $errors;
    }

    /**
     * @param array<string, int> $accepted
     */
    private function updateBaseline(ModuleBoundaryConfig $config, string $root, string $baseline, array $accepted, bool $allowGrow): int
    {
        $violations = $this->layerChecker->check($config, $root)['violations'];
        $applied = LayerBaseline::apply($accepted, $violations);
        $exists = file_exists($baseline);
        // Shrink-only by default: an existing baseline keeps the accepted findings that still occur (accepted ∩
        // current); new findings (enforce or warn) are listed and left out unless --allow-grow is given.
        $grow = !$exists || $allowGrow;
        $kept = $grow ? $violations : $applied['matched'];

        try {
            LayerBaseline::write($root, $baseline, LayerBaseline::render($kept));
        } catch (ConfigException $e) {
            fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

            return 2;
        }
        if (!$grow) {
            foreach ($applied['new'] as $violation) {
                fwrite($this->stdout, 'not added (fix it, or pass --allow-grow): ' . $violation->describe() . "\n");
            }
        }
        fwrite($this->stdout, \sprintf(
            "Baseline %s: %s (%d entr%s; %d added, %d removed%s).\n",
            $exists ? 'updated' : 'created',
            $baseline,
            \count($kept),
            1 === \count($kept) ? 'y' : 'ies',
            \count($kept) - $applied['baselined'],
            \count($applied['stale']),
            $grow || [] === $applied['new'] ? '' : \sprintf('; %d new finding(s) not added', \count($applied['new'])),
        ));

        return 0;
    }

    private function resolve(string $path, string $base): string
    {
        if (str_starts_with($path, '/') || str_contains($path, ':')) {
            return rtrim($path, '/');
        }

        return rtrim($base, '/') . '/' . rtrim($path, '/');
    }
}
