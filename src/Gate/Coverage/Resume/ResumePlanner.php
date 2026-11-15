<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * Decides between the full suite and a resume of the kept run, and which test files a resume reruns.
 *
 * Full suite when:
 * - forced, there is no kept run, or the kept run is from another format, project root, PHP, PHPUnit or
 *   php-code-coverage version;
 * - the kept run's tests failed and the JUnit log names no failing test case; or they failed at all, unless
 *   `--trust-junit-failures` (the log does not record data provider errors, runner warnings or `failOn*` issues);
 * - the marker policy says so: a resumed run already passed since the last full run (`--max-green-resumes`, default
 *   1), or too many resumes were chained (`--max-resumes`, default 5);
 * - any changed file is not a source or test file (configuration, migrations, Composer files, PHPUnit
 *   configuration, templates...), or is a non-test file under the test directories (bootstrap, helpers, fixtures);
 * - a source file was added, deleted or renamed, is not PHP, matches a `--full-glob` (container, kernel, bundle,
 *   migrations, fixtures), changed outside its function bodies ({@see PhpSkeleton}), or changed a function no test of
 *   the kept run executed (nothing records who calls it);
 * - more than `--max-src-files` source files changed (default 10);
 * - a test file uses another test file's result (`#[DependsExternal]`, `#[DependsOnClass]`, `#[Depends…('Other::t')]`,
 *   `@depends Other::t`): rerun without its producer, PHPUnit skips it, and a skipped test does not fail the run;
 * - there is nothing to rerun.
 *
 * A resume reruns, by test file: the tests that executed any line of a changed source file or of a class extending,
 * implementing or using one of its classes (transitively); every test file naming one of those classes (data
 * providers run outside the per-test coverage); the test files with no recorded coverage at all and those the
 * per-test data cannot attribute ({@see unattributableTests()}); the changed and new test files; the kept run's failed
 * test files; and the `--always` (gate) test files.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ResumePlanner
{
    private const MAX_REASONS = 5;

    /**
     * Test files that use another file's test result ({@see dependingTests()}): the full suite.
     */
    private const DEPENDS = '/\bDependsExternal\w*\s*\(|\bDepends\w*\s*\(\s*[^)]*::|@depends\s+\S+::/';

    /**
     * Test files whose per-test data cannot show the source code they depend on ({@see unattributableTests()}):
     * rerun on every source resume. Attributes are matched anywhere (grouped `#[Small, CoversClass(…)]`), and so is
     * the import of a coverage attribute, aliased too (`use …\Attributes\{Small, CoversClass as Target}`).
     */
    private const SOURCE_BLIND = '/\bCovers(?:Class|Method|Function|Trait|Namespace|ClassesThat\w+)\s*\(|\bCoversNothing\b'
        . '|\\\\Attributes\\\\Covers\w*|\bCovers\w+\s+as\b'
        . '|@covers(?:DefaultClass)?\b|\bBeforeClass\b|@beforeClass\b|\bsetUpBeforeClass\s*\('
        . '|\b(?:shell_exec|exec|proc_open|system|passthru|popen|pcntl_exec)\s*\(|\bProcess::from\w+\s*\(|\bPhpProcess\b'
        . '|\bnew\s+\\\\?(?:Symfony\\\\Component\\\\Process\\\\)?Process\b/';

    /**
     * @param array<string, string> $snapshot  the current files ({@see Snapshot::take()})
     * @param array<string, string> $toolchain the current php, phpunit and codeCoverage versions
     */
    public function plan(ResumeOptions $options, ?ResumeState $state, array $snapshot, array $toolchain): ResumeDecision
    {
        if ($options->forceFull) {
            return ResumeDecision::full(['forced (--force-full, FORCE_COVERAGE or COVERAGE_RESUME=0)']);
        }
        if (!$state instanceof ResumeState) {
            return ResumeDecision::full(['no kept run']);
        }
        $reasons = $this->stateReasons($options, $state, $toolchain);
        if ([] !== $reasons) {
            return ResumeDecision::full($reasons);
        }

        $diff = Snapshot::diff($state->snapshot, $snapshot);
        $changedSrc = [];
        $changedTests = [];
        $deletedTests = [];
        foreach ($diff['added'] as $path) {
            if ($options->isTestFile($path)) {
                $changedTests[] = $path;
            } else {
                $reasons[] = $this->otherChange($options, $path, 'added');
            }
        }
        foreach ($diff['deleted'] as $path) {
            if ($options->isTestFile($path)) {
                $deletedTests[] = $path;
            } else {
                $reasons[] = $this->otherChange($options, $path, 'deleted');
            }
        }
        foreach ($diff['modified'] as $path) {
            if ($options->isTestFile($path)) {
                $changedTests[] = $path;
            } elseif ($options->isSource($path)) {
                $changedSrc[] = $path;
            } else {
                $reasons[] = $this->otherChange($options, $path, 'changed');
            }
        }
        if (\count($changedSrc) > $options->maxSrcFiles) {
            $reasons[] = \sprintf('%d source files changed (more than --max-src-files=%d)', \count($changedSrc), $options->maxSrcFiles);
        }
        foreach ($changedSrc as $path) {
            array_push($reasons, ...$this->sourceReasons($options, $state, $path));
        }
        foreach ($this->dependingTests($options, $snapshot) as $path) {
            $reasons[] = "{$path} uses another test file's result (DependsExternal, DependsOnClass, Depends on Other::test): rerun without its producer, PHPUnit would skip it";
        }
        if ([] !== $reasons) {
            return ResumeDecision::full(\array_slice($reasons, 0, self::MAX_REASONS));
        }

        $selected = [];
        foreach ($this->affectedSources($state, $changedSrc) as $path) {
            foreach ($state->testsOf($path) as $test) {
                $selected[$test] = true;
            }
        }
        if ([] !== $changedSrc) {
            foreach ($state->unattributedTests() as $test) {
                $selected[$test] = true;
            }
            foreach ($this->testsNaming($options, $state, $changedSrc, $snapshot) as $test) {
                $selected[$test] = true;
            }
        }
        if ([] !== $changedSrc) {
            foreach ($this->unattributableTests($options, $snapshot) as $test) {
                $selected[$test] = true;
            }
        }
        foreach ([...$changedTests, ...$state->failed] as $test) {
            $selected[$test] = true;
        }
        foreach (array_keys($snapshot) as $path) {
            if ($options->isAlways((string) $path)) {
                $selected[$path] = true;
            }
        }
        $tests = array_values(array_filter(array_map(strval(...), array_keys($selected)), static fn (string $test): bool => isset($snapshot[$test])));
        if ([] === $tests) {
            return ResumeDecision::full(['nothing to rerun: no failed test, no changed test or source file']);
        }
        sort($tests);

        return ResumeDecision::resume($tests, $changedSrc, $deletedTests);
    }

    /**
     * @param array<string, string> $toolchain
     *
     * @return list<string>
     */
    private function stateReasons(ResumeOptions $options, ResumeState $state, array $toolchain): array
    {
        $reasons = [];
        if ($state->root !== $options->root) {
            $reasons[] = "kept run from another project root ({$state->root})";
        }
        foreach (['php' => 'PHP', 'phpunit' => 'PHPUnit', 'codeCoverage' => 'php-code-coverage'] as $key => $label) {
            $kept = $state->toolchain[$key] ?? '';
            $now = $toolchain[$key] ?? '';
            if ($kept !== $now) {
                $reasons[] = "kept run made with {$label} {$kept}, now {$now}";
            }
        }
        if ('failed' === $state->testsStatus && [] === $state->failed) {
            $reasons[] = 'the kept run\'s tests failed outside any test case the JUnit log names (failOn* issues, runner warnings, setUpBeforeClass or data provider errors)';
        } elseif ('failed' === $state->testsStatus && !$options->trustJunitFailures) {
            $reasons[] = 'the kept run\'s tests failed, and the JUnit log may not record every cause (data provider errors, runner warnings, failOn* issues): --trust-junit-failures reruns only the failing files';
        }
        if ($state->greenResumes >= $options->maxGreenResumes) {
            $reasons[] = \sprintf('%d resumed run(s) already passed since the last full run (--max-green-resumes=%d)', $state->greenResumes, $options->maxGreenResumes);
        }
        if ($state->resumes >= $options->maxResumes) {
            $reasons[] = \sprintf('%d resumes chained since the last full run (--max-resumes=%d)', $state->resumes, $options->maxResumes);
        }

        return $reasons;
    }

    private function otherChange(ResumeOptions $options, string $path, string $how): string
    {
        if ($options->isSource($path)) {
            return "source file {$path} {$how} (a new, deleted or renamed source file)";
        }
        if ($options->isTestDir($path)) {
            return "{$path} {$how}: not a test class, may be shared by any test (bootstrap, helper, fixture)";
        }

        return "{$path} {$how}: not a source or test file (configuration, dependencies, templates...)";
    }

    /**
     * @return list<string>
     */
    private function sourceReasons(ResumeOptions $options, ResumeState $state, string $path): array
    {
        if (!str_ends_with($path, '.php')) {
            return ["source file {$path} changed and is not PHP"];
        }
        if ($options->isFullGlob($path)) {
            return ["{$path} changed: wiring or code shared by every test (--full-glob)"];
        }
        $kept = $state->src[$path] ?? null;
        $code = file_get_contents($options->root . '/' . $path);
        if (null === $kept || false === $code) {
            return ["{$path} changed and has no kept analysis"];
        }
        $now = PhpSkeleton::analyse($code);
        if ($now->skeleton !== $kept['skeleton'] || \count($now->bodies) !== \count($kept['bodies'])) {
            return ["{$path} changed outside function bodies (signature, property, constant, attribute, import, inheritance)"];
        }
        foreach ($now->bodies as $i => [, , $hash]) {
            [$first, $last, $keptHash, $executable] = $kept['bodies'][$i];
            if ($hash !== $keptHash && !$executable) {
                return ["{$path}: the function at lines {$first}-{$last} changed and no test of the kept run executed it (bootstrap, extension, warm-up or subprocess code, or an empty body), so no test is known to call it"];
            }
        }

        return [];
    }

    /**
     * The changed source files plus, transitively, every source file declaring a class that extends, implements or
     * uses (trait) a class declared in them.
     *
     * @param list<string> $changedSrc
     *
     * @return list<string>
     */
    private function affectedSources(ResumeState $state, array $changedSrc): array
    {
        $files = array_fill_keys($changedSrc, true);
        $names = $this->declaredNames($state, $changedSrc);
        do {
            $added = false;
            foreach ($state->src as $path => $info) {
                if (!isset($files[$path]) && [] !== array_intersect($info['parents'], $names)) {
                    $files[$path] = true;
                    $names = [...$names, ...$info['declares']];
                    $added = true;
                }
            }
        } while ($added);

        return array_map(strval(...), array_keys($files));
    }

    /**
     * Test files (current content) whose source names a class declared in a changed source file.
     *
     * @param list<string>          $changedSrc
     * @param array<string, string> $snapshot
     *
     * @return list<string>
     */
    private function testsNaming(ResumeOptions $options, ResumeState $state, array $changedSrc, array $snapshot): array
    {
        if (!$options->nameReferences) {
            return [];
        }
        $names = $this->declaredNames($state, $changedSrc);
        if ([] === $names) {
            return [];
        }
        $pattern = '/\b(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $names)) . ')\b/';
        $tests = [];
        foreach (array_keys($snapshot) as $path) {
            $path = (string) $path;
            if ($options->isTestFile($path) && 1 === preg_match($pattern, (string) file_get_contents($options->root . '/' . $path))) {
                $tests[] = $path;
            }
        }

        return $tests;
    }

    /**
     * Test files that consume another test file's result (`#[DependsExternal]`, `#[DependsOnClass]`,
     * `#[Depends*('Other::t')]`, `@depends Other::t`). A resume cannot rerun them: without their producer in the same
     * run PHPUnit skips them, and a skipped test does not fail the run (audit QF3-M1). Their producer can change
     * (test-only) or run other code than they do, so any of them anywhere means the full suite.
     *
     * Detection is textual and per test file.
     *
     * @param array<string, string> $snapshot
     *
     * @return list<string>
     */
    private function dependingTests(ResumeOptions $options, array $snapshot): array
    {
        $tests = [];
        foreach (array_keys($snapshot) as $path) {
            $path = (string) $path;
            if ($options->isTestFile($path) && 1 === preg_match(self::DEPENDS, (string) file_get_contents($options->root . '/' . $path))) {
                $tests[] = $path;
            }
        }

        return $tests;
    }

    /**
     * Test files whose per-test data cannot show the source code they depend on, rerun on a source resume: coverage
     * metadata (`#[CoversClass]`, `@covers`…: php-code-coverage keeps only the covered targets of such a test),
     * `setUpBeforeClass()` / `#[BeforeClass]` (runs outside any test's coverage), and subprocesses (`shell_exec`,
     * `exec`, `proc_open`, `system`, `passthru`, `popen`, `Process`, `PhpProcess`, `Process::fromShellCommandline()`,
     * backticks): code no coverage of this process records.
     *
     * Detection is textual and per test file: a trait, a base class or a helper elsewhere is not seen.
     *
     * @param array<string, string> $snapshot
     *
     * @return list<string>
     */
    private function unattributableTests(ResumeOptions $options, array $snapshot): array
    {
        $tests = [];
        foreach (array_keys($snapshot) as $path) {
            $path = (string) $path;
            if (!$options->isTestFile($path)) {
                continue;
            }
            $code = (string) file_get_contents($options->root . '/' . $path);
            if (1 === preg_match(self::SOURCE_BLIND, $code) || $this->hasBackticks($code)) {
                $tests[] = $path;
            }
        }

        return $tests;
    }

    /**
     * A shell command in backticks (a token, not a backtick in a comment or a string).
     */
    private function hasBackticks(string $code): bool
    {
        if (!str_contains($code, '`')) {
            return false;
        }
        foreach (\PhpToken::tokenize($code) as $token) {
            if ('`' === $token->text) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function declaredNames(ResumeState $state, array $files): array
    {
        $names = [];
        foreach ($files as $file) {
            array_push($names, ...($state->src[$file]['declares'] ?? []));
        }

        return array_values(array_unique($names));
    }
}
