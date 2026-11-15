<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * The test classes of a JUnit log (PHPUnit or ParaTest `--log-junit`) with their files, the files of the tests that
 * failed or errored (a test-class suite with no test case counts as failed: its `setUpBeforeClass()` failed), and the
 * skipped or incomplete test cases.
 *
 * The log does not record every reason PHPUnit fails a run: `failOn*` issues (deprecations, notices, warnings…),
 * runner warnings and data provider errors leave no failing test case. The caller keeps the tests' exit status for
 * that ({@see ResumeState::$testsStatus}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class JunitTests
{
    /**
     * @param array<string, string> $classes test class => file (relative to the root when inside it)
     * @param list<string>          $failed  files with a failing or erroring test
     * @param list<string>          $skipped skipped or incomplete test cases (`Class::name`)
     */
    public function __construct(
        public array $classes,
        public array $failed,
        public array $skipped = [],
    ) {
    }

    /**
     * @throws \RuntimeException when the log is missing or is not XML
     */
    public static function read(string $file, string $root): self
    {
        $xml = is_file($file) ? @simplexml_load_file($file) : false;
        if (false === $xml) {
            throw new \RuntimeException("JUnit log not found or not XML: {$file}");
        }
        $classes = [];
        $failed = [];
        $skipped = [];
        foreach ($xml->xpath('//testcase[@file]') ?: [] as $case) {
            $path = self::relative((string) $case['file'], $root);
            $class = (string) $case['class'];
            if ('' !== $class) {
                $classes[$class] = $path;
            }
            if (isset($case->failure) || isset($case->error)) {
                $failed[$path] = true;
            } elseif (isset($case->skipped)) {
                $skipped[$class . '::' . $case['name']] = true;
            }
        }
        // A test class whose setUpBeforeClass() (or similar) failed: a suite with its file and no test case.
        foreach ($xml->xpath('//testsuite[@file and @tests="0"]') ?: [] as $suite) {
            $failed[self::relative((string) $suite['file'], $root)] = true;
        }
        ksort($classes);
        ksort($skipped);

        return new self($classes, array_keys($failed), array_map(strval(...), array_keys($skipped)));
    }

    public static function relative(string $path, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, \strlen($prefix)) : $path;
    }
}
