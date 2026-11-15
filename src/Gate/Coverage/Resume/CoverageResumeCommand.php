<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

use PHPUnit\Runner\Version;

/**
 * `bin/coverage-resume`: rerun only what a small change can affect after a coverage run, and merge it into the kept
 * run with the per-test coverage data, so a failed 100 % gate fixed by a test or a small source fix does not cost a
 * full suite.
 *
 *   plan    decide (print the test files to rerun, exit 0) or ask for the full suite (exit 3, reasons on stderr);
 *           remembers the decision and the files as they are now in <state>/pending.json
 *   merge   after the resumed run: drop the kept data of the changed source files and every contribution of the
 *           rerun / deleted test classes, add the new run's data; write the merged --coverage-php file and Clover
 *   save    after any run: keep its per-test data and analysis as the base of the next resume (nothing is kept when
 *           files changed during the run)
 *   status  print the stamp of the last saved run ("coverage: full" or "coverage: resumed (k test files, m source
 *           files)") when it passed and the files are still those it ran on; otherwise "coverage: unknown …", exit 1
 *   forget  remove the kept state
 *
 * See {@see ResumePlanner} for the rules and docs/USAGE.md ("Coverage resume") for the workflow.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageResumeCommand
{
    public const USAGE = <<<'TXT'
        Usage: coverage-resume plan   [<options>]
               coverage-resume merge  --coverage=<new.cov> --junit=<junit.xml> --clover=<out.xml> [--coverage-out=<file>] [<options>]
               coverage-resume save   --coverage=<run.cov> --junit=<junit.xml> --tests-status=passed|failed
                                      --status=passed|failed [--commit=<sha>] [<options>]
               coverage-resume status [<options>]
               coverage-resume forget [<options>]

        plan prints the test files a resume reruns (exit 0) or the reasons for the full suite (stderr, exit 3).
        Run the tests with --coverage-php and --log-junit; after a resume, merge into the kept run; after any run,
        save it (--tests-status: PHPUnit's exit status; --status: the tests and the coverage gate). The merged
        Clover report replaces the full run's. status prints the stamp of the saved run for the files as they are now.
          --state=<dir>              kept state (default var/coverage/resume)
          --src=<dir>                source directory, repeatable (default src)
          --tests=<dir>              test directory, repeatable (default tests)
          --test-suffix=<suffix>     test class files (default Test.php); other files under the test dirs are shared
          --max-src-files=<n>        full suite above n changed source files (default 10)
          --max-green-resumes=<n>    full suite once n resumed runs passed since the last full run (default 1)
          --max-resumes=<n>          full suite after n chained resumes (default 5)
          --always=<glob>            test files always rerun (gate tests), repeatable
          --full-glob=<glob>         source files whose change means the full suite, repeatable (replaces the
                                     defaults: DependencyInjection, *Kernel.php, *Bundle.php, Migrations, Fixtures...)
          --exclude=<pattern>        paths left out of the change detection, repeatable (`dir/` or fnmatch)
          --input=<path>             paths always in the change detection, repeatable (vendor/composer/installed.json
                                     and .env files are by default)
          --files-from=<file>        the project files to compare, one per line (e.g. git ls-files -co
                                     --exclude-standard); default: every file under the root
          --no-name-references       do not rerun the test files that only name a changed class
          --force-full               always the full suite (also FORCE_COVERAGE=1 or COVERAGE_RESUME=0)
          --trust-junit-failures     after a kept run whose tests failed, rerun only the failing files although the
                                     PHPUnit configuration sets failOn* (issues the JUnit log does not record)
          --phpunit-config=<file>    PHPUnit configuration read for failOn*, repeatable (default: phpunit.xml,
                                     phpunit.xml.dist, phpunit.dist.xml; the first found)
        Exit codes: 0 OK (plan: resume), 3 full suite needed, 1 run failure (missing or unreadable data), 2 usage.
        TXT;

    public const EXIT_FULL = 3;

    private readonly CoverageFormat $format;

    /** @var \Closure(): array<string, string> */
    private readonly \Closure $toolchain;

    /**
     * @param resource                                 $stdout
     * @param resource                                 $stderr
     * @param array<string, string>                    $env
     * @param (\Closure(): array<string, string>)|null $toolchain current php / phpunit / codeCoverage versions
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
        private readonly array $env = [],
        ?CoverageFormat $format = null,
        ?\Closure $toolchain = null,
    ) {
        $this->format = $format ?? new CoverageFormat();
        $this->toolchain = $toolchain ?? self::currentToolchain(...);
    }

    /**
     * @return array<string, string>
     */
    public static function currentToolchain(): array
    {
        $version = static fn (string $class): string => class_exists($class) && method_exists($class, 'id') ? (string) $class::id() : 'none';

        return [
            'php' => \PHP_VERSION,
            'phpunit' => $version(Version::class),
            'codeCoverage' => $version(\SebastianBergmann\CodeCoverage\Version::class),
        ];
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $action = $args[0] ?? '';
        if ('' === $action || '-h' === $action || '--help' === $action) {
            fwrite('' === $action ? $this->stderr : $this->stdout, self::USAGE . "\n");

            return '' === $action ? 2 : 0;
        }

        try {
            [$options, $rest] = ResumeOptions::parse($this->cwd, \array_slice($args, 1), $this->env);
        } catch (\InvalidArgumentException $e) {
            return $this->usageError($e->getMessage());
        }
        $allowed = ['plan' => [], 'status' => [], 'forget' => [], 'merge' => ['coverage', 'junit', 'clover', 'coverage-out'], 'save' => ['coverage', 'junit', 'status', 'tests-status', 'commit']];
        if (!isset($allowed[$action])) {
            return $this->usageError("Unknown action {$action}");
        }
        $unknown = array_diff(array_keys($rest), $allowed[$action]);
        if ([] !== $unknown) {
            return $this->usageError('Unknown option --' . implode(', --', $unknown) . " for {$action}");
        }

        try {
            return match ($action) {
                'plan' => $this->plan($options),
                'merge' => $this->merge($options, $rest),
                'save' => $this->save($options, $rest),
                'status' => $this->status($options),
                default => $this->forget($options),
            };
        } catch (\RuntimeException|\UnexpectedValueException $e) {
            fwrite($this->stderr, "coverage-resume: {$e->getMessage()}\n");

            return 1;
        }
    }

    private function plan(ResumeOptions $options): int
    {
        $snapshot = $this->snapshot($options);
        $toolchain = ($this->toolchain)();
        $decision = (new ResumePlanner())->plan($options, $this->state($options), $snapshot, $toolchain);
        $this->writeJson($options->statePath('pending.json'), [
            'format' => ResumeState::FORMAT,
            'resume' => $decision->resume,
            'testFiles' => $decision->testFiles,
            'changedSrc' => $decision->changedSrc,
            'deletedTests' => $decision->deletedTests,
            'stamp' => $decision->stamp(),
            'snapshot' => $snapshot,
            'toolchain' => $toolchain,
        ]);
        if (!$decision->resume) {
            fwrite($this->stderr, "coverage-resume: full suite —\n  " . implode("\n  ", $decision->reasons) . "\n");

            return self::EXIT_FULL;
        }
        fwrite($this->stderr, \sprintf(
            "coverage-resume: resumed — %d test file(s) for %d changed source file(s)%s\n",
            \count($decision->testFiles),
            \count($decision->changedSrc),
            [] === $decision->changedSrc ? '' : ': ' . implode(', ', $decision->changedSrc),
        ));
        fwrite($this->stdout, implode("\n", $decision->testFiles) . "\n");

        return 0;
    }

    /**
     * @param array<string, string> $rest
     */
    private function merge(ResumeOptions $options, array $rest): int
    {
        $pending = $this->pending($options);
        $state = $this->state($options);
        if (!$pending['resume'] || !$state instanceof ResumeState) {
            throw new \RuntimeException('nothing to merge: the last plan was not a resume');
        }
        $kept = $this->format->load($options->statePath('coverage.cov'));
        $new = $this->format->load($this->required($rest, 'coverage', $options));
        $junit = JunitTests::read($this->required($rest, 'junit', $options), $options->root);
        $refusal = match (true) {
            $kept->driver !== $new->driver => "coverage driver changed ({$kept->driver} → {$new->driver})",
            $kept->hasBranchData || $new->hasBranchData => 'branch / path coverage data cannot be merged',
            default => null,
        };
        if (null !== $refusal) {
            fwrite($this->stderr, "coverage-resume: cannot merge, run the full suite — {$refusal}\n");

            return self::EXIT_FULL;
        }

        $dropClasses = $this->droppedClasses($state, $pending, $junit, $new->coverage);
        $dropFiles = array_map(static fn (string $path): string => $options->root . '/' . $path, $pending['changedSrc']);
        $merged = $kept->coverage->withoutFiles($dropFiles)->withoutTestClasses($dropClasses)->merge($new->coverage);
        $coverageOut = $rest['coverage-out'] ?? $options->statePath('merged.cov');
        $this->format->write($new, $merged, $this->absolute($coverageOut, $options), $this->required($rest, 'clover', $options));
        [$executed, $executable] = $merged->lineTotals();
        fwrite($this->stdout, \sprintf(
            "coverage-resume: merged %d test file(s) into the kept run, dropped %d changed source file(s) — lines %d/%d covered\n",
            \count($pending['testFiles']),
            \count($dropFiles),
            $executed,
            $executable,
        ));

        return 0;
    }

    /**
     * @param array<string, string> $rest
     */
    private function save(ResumeOptions $options, array $rest): int
    {
        $status = $rest['status'] ?? '';
        $testsStatus = $rest['tests-status'] ?? '';
        if (!\in_array($status, ['passed', 'failed'], true) || !\in_array($testsStatus, ['passed', 'failed'], true)) {
            return $this->usageError('save needs --tests-status=passed|failed and --status=passed|failed');
        }
        if ('failed' === $testsStatus) {
            $status = 'failed';
        }
        $pending = $this->pending($options);
        if ($this->snapshot($options) !== $pending['snapshot']) {
            $this->forget($options, quiet: true);
            fwrite($this->stderr, "coverage-resume: files changed during the run — nothing kept, the next run is the full suite\n");

            return 0;
        }
        $state = $pending['resume'] ? $this->state($options) : null;
        if ($pending['resume'] && !$state instanceof ResumeState) {
            throw new \RuntimeException('the kept run disappeared during the resume');
        }
        $coverageFile = $this->required($rest, 'coverage', $options);
        $loaded = $this->format->load($coverageFile);
        $junit = JunitTests::read($this->required($rest, 'junit', $options), $options->root);

        $classes = $junit->classes;
        if ($state instanceof ResumeState) {
            $dropped = array_flip($this->droppedClasses($state, $pending, $junit, null));
            foreach ($state->testClasses as $class => $i) {
                if (!isset($dropped[$class])) {
                    $classes[$class] ??= $state->testFiles[$i];
                }
            }
        }
        ksort($classes);
        $testFiles = array_values(array_unique(array_values($classes)));
        $fileIndex = array_flip($testFiles);
        $index = [];
        foreach ($loaded->coverage->lines as $file => $lines) {
            $tests = [];
            foreach ($loaded->coverage->testsOfFile($file) as $id) {
                $class = PerTestCoverage::testClass($id);
                $testFile = $classes[$class] ?? JunitTests::relative($class, $options->root);
                if (!isset($fileIndex[$testFile])) {
                    $fileIndex[$testFile] = \count($testFiles);
                    $testFiles[] = $testFile;
                }
                $tests[$fileIndex[$testFile]] = true;
            }
            $index[JunitTests::relative($file, $options->root)] = array_keys($tests);
        }

        $green = 'passed' === $status;
        $resumed = $state instanceof ResumeState;
        $commit = $rest['commit'] ?? '';
        $new = new ResumeState(
            $resumed ? 'resumed' : 'full',
            $status,
            $testsStatus,
            $resumed ? $state->resumes + 1 : 0,
            $resumed ? $state->greenResumes + ($green ? 1 : 0) : 0,
            $options->root,
            $commit,
            $resumed ? $state->fullCommit : $commit,
            gmdate('Y-m-d\TH:i:s\Z'),
            [...$pending['toolchain'], 'driver' => $loaded->driver],
            $pending['snapshot'],
            $this->sourceAnalysis($options, $pending['snapshot'], $loaded->coverage, $state),
            $testFiles,
            array_map(static fn (string $file): int => $fileIndex[$file], $classes),
            $index,
            $junit->failed,
            $pending['stamp'],
            (string) sha1_file($coverageFile),
        );
        // coverage.cov first, then state.json, each through a rename: an interrupted save leaves a state whose
        // coverage hash does not match, which is refused.
        if (realpath($coverageFile) !== realpath($options->statePath('coverage.cov'))) {
            $temporary = $options->statePath('coverage.cov.' . getmypid() . '.tmp');
            copy($coverageFile, $temporary);
            rename($temporary, $options->statePath('coverage.cov'));
        }
        $this->writeJson($options->statePath('state.json'), $new->toArray());
        fwrite($this->stdout, \sprintf(
            "coverage-resume: kept the %s run (%s, %d test file(s)) as the base of the next resume%s\n",
            $new->mode,
            $status,
            \count($testFiles),
            $resumed ? \sprintf('; %d green resume(s) since the last full run', $new->greenResumes) : '',
        ));

        return 0;
    }

    /**
     * The stamp of the last saved run, only while the files are those it ran on and it passed: the stamp a tag
     * message may carry. Nothing else (a plan, an interrupted run) changes it.
     */
    private function status(ResumeOptions $options): int
    {
        $state = $this->state($options);
        $problem = match (true) {
            !$state instanceof ResumeState => 'no saved run',
            $state->snapshot !== $this->snapshot($options) => 'files changed since the saved run',
            'passed' !== $state->status => "the saved run failed: {$state->stamp}",
            default => null,
        };
        if (null !== $problem) {
            fwrite($this->stdout, "coverage: unknown ({$problem})\n");

            return 1;
        }
        fwrite($this->stdout, $state->stamp . "\n");

        return 0;
    }

    private function forget(ResumeOptions $options, bool $quiet = false): int
    {
        foreach (['state.json', 'coverage.cov', 'merged.cov', 'pending.json', 'stamp.txt'] as $file) {
            @unlink($options->statePath($file));
        }
        if (!$quiet) {
            fwrite($this->stdout, "coverage-resume: kept state removed\n");
        }

        return 0;
    }

    /**
     * Test classes whose kept contributions go: those of the rerun and deleted test files, and every class the new
     * run executed (its JUnit log, and its coverage data when given).
     *
     * @param array{testFiles: list<string>, deletedTests: list<string>} $pending
     *
     * @return list<string>
     */
    private function droppedClasses(ResumeState $state, array $pending, JunitTests $junit, ?PerTestCoverage $new): array
    {
        $files = [...$pending['testFiles'], ...$pending['deletedTests']];
        $classes = [
            ...$state->classesOf($files),
            ...array_map(strval(...), array_keys($junit->classes)),
            // A PHPT test's id is its path.
            ...array_map(static fn (string $file): string => $state->root . '/' . $file, $files),
        ];
        foreach (array_keys($new->tests ?? []) as $id) {
            $classes[] = PerTestCoverage::testClass((string) $id);
        }

        return array_values(array_unique($classes));
    }

    /**
     * @param array<string, string> $snapshot
     * @param ResumeState|null      $previous analysis reused for the files whose content did not change
     *
     * @return array<string, array{skeleton: string, bodies: list<array{0: int, 1: int, 2: string, 3: bool}>, declares: list<string>, parents: list<string>}>
     */
    private function sourceAnalysis(ResumeOptions $options, array $snapshot, PerTestCoverage $coverage, ?ResumeState $previous): array
    {
        $src = [];
        foreach ($snapshot as $path => $hash) {
            $path = (string) $path;
            if (!$options->isSource($path) || !str_ends_with($path, '.php')) {
                continue;
            }
            $old = $previous?->src[$path] ?? null;
            if (null !== $old && ($previous->snapshot[$path] ?? null) === $hash) {
                $analysis = new PhpSkeleton($old['skeleton'], array_map(static fn (array $body): array => [$body[0], $body[1], $body[2]], $old['bodies']), $old['declares'], $old['parents']);
            } else {
                $analysis = PhpSkeleton::analyse((string) file_get_contents($options->root . '/' . $path));
            }
            $absolute = $options->root . '/' . $path;
            $src[$path] = [
                'skeleton' => $analysis->skeleton,
                'bodies' => array_map(static fn (array $body): array => [$body[0], $body[1], $body[2], $coverage->hasExecutedLine($absolute, $body[0], $body[1])], $analysis->bodies),
                'declares' => $analysis->declares,
                'parents' => $analysis->parents,
            ];
        }

        return $src;
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(ResumeOptions $options): array
    {
        return Snapshot::take($options->root, $options->files, $options->excludes, $options->inputs);
    }

    private function state(ResumeOptions $options): ?ResumeState
    {
        $data = $this->readJson($options->statePath('state.json'));
        if (null === $data || !is_file($options->statePath('coverage.cov'))) {
            return null;
        }

        try {
            $state = ResumeState::fromArray($data);
        } catch (\UnexpectedValueException) {
            return null;
        }

        // A coverage file of another run (an interrupted save, a copy) is not this state's.
        return sha1_file($options->statePath('coverage.cov')) === $state->coverageHash ? $state : null;
    }

    /**
     * @return array{resume: bool, testFiles: list<string>, changedSrc: list<string>, deletedTests: list<string>, stamp: string, snapshot: array<string, string>, toolchain: array<string, string>}
     */
    private function pending(ResumeOptions $options): array
    {
        $data = $this->readJson($options->statePath('pending.json'));
        if (null === $data || ResumeState::FORMAT !== ($data['format'] ?? null)) {
            throw new \RuntimeException('no plan recorded: run `coverage-resume plan` before the tests');
        }

        /* @phpstan-ignore-next-line return.type (trusted file written by plan()) */
        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        $content = @file_get_contents($file);
        $data = false === $content ? null : json_decode($content, true);

        return \is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $file, array $data): void
    {
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }
        $temporary = $file . '.' . getmypid() . '.tmp';
        file_put_contents($temporary, json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n");
        rename($temporary, $file);
    }

    /**
     * @param array<string, string> $rest
     */
    private function required(array $rest, string $name, ResumeOptions $options): string
    {
        if ('' === ($rest[$name] ?? '')) {
            throw new \RuntimeException("--{$name}=<file> is required");
        }

        return $this->absolute($rest[$name], $options);
    }

    private function absolute(string $path, ResumeOptions $options): string
    {
        return str_starts_with($path, '/') ? $path : $options->root . '/' . $path;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
