<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * Options of `bin/coverage-resume` (see {@see CoverageResumeCommand::USAGE}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class ResumeOptions
{
    /**
     * Source files whose change always means the full suite: container / kernel / bundle wiring and code shared by
     * every test (fixtures, migrations). A change there can alter tests that never executed the changed lines.
     */
    public const DEFAULT_FULL_GLOBS = [
        '*/DependencyInjection/*',
        '*Kernel.php',
        '*Bundle.php',
        '*/Migrations/*',
        '*/DataFixtures/*',
        '*/Fixtures/*',
        '*/Resources/config/*',
    ];

    public const DEFAULT_EXCLUDES = [
        '.git/', 'vendor/', 'node_modules/', 'var/', '.phpunit.cache/', '.phpunit.result.cache', 'coverage/',
        'coverage.xml', '.php-cs-fixer.cache', '.php-cs-fixer.php.cache', '.idea/', '.vscode/',
    ];

    public const DEFAULT_INPUTS = [
        'vendor/composer/installed.json', '.env', '.env.local', '.env.test', '.env.test.local',
    ];

    /**
     * @param list<string>      $srcDirs
     * @param list<string>      $testDirs
     * @param list<string>      $always    test file globs always rerun (gate tests)
     * @param list<string>      $fullGlobs
     * @param list<string>      $excludes
     * @param list<string>      $inputs
     * @param list<string>|null $files     the --files-from list
     */
    public function __construct(
        public string $root,
        public string $stateDir = 'var/coverage/resume',
        public array $srcDirs = ['src'],
        public array $testDirs = ['tests'],
        public string $testSuffix = 'Test.php',
        public int $maxSrcFiles = 10,
        public int $maxGreenResumes = 1,
        public int $maxResumes = 5,
        public array $always = [],
        public array $fullGlobs = self::DEFAULT_FULL_GLOBS,
        public array $excludes = self::DEFAULT_EXCLUDES,
        public array $inputs = self::DEFAULT_INPUTS,
        public ?array $files = null,
        public bool $nameReferences = true,
        public bool $forceFull = false,
    ) {
    }

    /**
     * @param list<string>          $args options (`--name=value` or flags)
     * @param array<string, string> $env  reads COVERAGE_RESUME (0 / no / off: always full) and FORCE_COVERAGE
     *
     * @throws \InvalidArgumentException on a bad value
     *
     * @return array{0: self, 1: array<string, string>} the options and the command-specific ones left over
     */
    public static function parse(string $root, array $args, array $env = []): array
    {
        $single = ['state' => 'var/coverage/resume', 'test-suffix' => 'Test.php', 'max-src-files' => '10', 'max-green-resumes' => '1', 'max-resumes' => '5'];
        $lists = ['src' => [], 'tests' => [], 'always' => [], 'full-glob' => [], 'exclude' => [], 'input' => []];
        $flags = ['no-name-references' => false, 'force-full' => false];
        $filesFrom = null;
        $rest = [];
        foreach ($args as $arg) {
            if (isset($flags[substr($arg, 2)]) && str_starts_with($arg, '--')) {
                $flags[substr($arg, 2)] = true;
                continue;
            }
            if (1 !== preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $match)) {
                throw new \InvalidArgumentException("Unknown argument {$arg}");
            }
            [, $name, $value] = $match;
            if (isset($lists[$name])) {
                $lists[$name][] = $value;
            } elseif (isset($single[$name])) {
                $single[$name] = $value;
            } elseif ('files-from' === $name) {
                $filesFrom = $value;
            } else {
                $rest[$name] = $value;
            }
        }
        $numbers = [];
        foreach (['max-src-files', 'max-green-resumes', 'max-resumes'] as $name) {
            if (1 !== preg_match('/^\d+$/', $single[$name])) {
                throw new \InvalidArgumentException("--{$name} must be a whole number");
            }
            $numbers[$name] = (int) $single[$name];
        }
        $files = null;
        if (null !== $filesFrom) {
            $path = str_starts_with($filesFrom, '/') ? $filesFrom : $root . '/' . $filesFrom;
            $content = is_file($path) ? file_get_contents($path) : false;
            if (false === $content) {
                throw new \InvalidArgumentException("--files-from file not found: {$filesFrom}");
            }
            $files = array_values(array_filter(array_map(trim(...), explode("\n", $content)), static fn (string $line): bool => '' !== $line));
        }
        $state = rtrim($single['state'], '/');
        $resume = strtolower($env['COVERAGE_RESUME'] ?? '');
        $force = $flags['force-full'] || \in_array($resume, ['0', 'no', 'off', 'false'], true) || '' !== ($env['FORCE_COVERAGE'] ?? '');

        return [new self(
            $root,
            $state,
            [] === $lists['src'] ? ['src'] : array_map(self::dir(...), $lists['src']),
            [] === $lists['tests'] ? ['tests'] : array_map(self::dir(...), $lists['tests']),
            $single['test-suffix'],
            $numbers['max-src-files'],
            $numbers['max-green-resumes'],
            $numbers['max-resumes'],
            $lists['always'],
            [] === $lists['full-glob'] ? self::DEFAULT_FULL_GLOBS : array_values(array_filter($lists['full-glob'], static fn (string $glob): bool => '' !== $glob)),
            [...self::DEFAULT_EXCLUDES, $state . '/', ...$lists['exclude']],
            [...self::DEFAULT_INPUTS, ...$lists['input']],
            $files,
            !$flags['no-name-references'],
            $force,
        ), $rest];
    }

    public function statePath(string $file): string
    {
        $dir = str_starts_with($this->stateDir, '/') ? $this->stateDir : $this->root . '/' . $this->stateDir;

        return $dir . '/' . $file;
    }

    public function isSource(string $path): bool
    {
        return self::under($path, $this->srcDirs);
    }

    public function isTestDir(string $path): bool
    {
        return self::under($path, $this->testDirs);
    }

    public function isTestFile(string $path): bool
    {
        return $this->isTestDir($path) && str_ends_with($path, $this->testSuffix);
    }

    public function isFullGlob(string $path): bool
    {
        foreach ($this->fullGlobs as $glob) {
            if (fnmatch($glob, $path) || fnmatch($glob, '/' . $path)) {
                return true;
            }
        }

        return false;
    }

    public function isAlways(string $path): bool
    {
        foreach ($this->always as $glob) {
            if (fnmatch($glob, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $dirs
     */
    private static function under(string $path, array $dirs): bool
    {
        foreach ($dirs as $dir) {
            if (str_starts_with($path, $dir . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function dir(string $dir): string
    {
        return trim(str_starts_with($dir, './') ? substr($dir, 2) : $dir, '/');
    }
}
