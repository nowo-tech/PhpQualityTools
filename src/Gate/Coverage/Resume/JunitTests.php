<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * The test classes of a JUnit log (PHPUnit or ParaTest `--log-junit`) with their files, and the files of the tests
 * that failed or errored (a test-class suite with no test case counts as failed: its `setUpBeforeClass()` failed).
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
     */
    public function __construct(
        public array $classes,
        public array $failed,
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
        foreach ($xml->xpath('//testcase[@file]') ?: [] as $case) {
            $path = self::relative((string) $case['file'], $root);
            $class = (string) $case['class'];
            if ('' !== $class) {
                $classes[$class] = $path;
            }
            if (isset($case->failure) || isset($case->error)) {
                $failed[$path] = true;
            }
        }
        // A test class whose setUpBeforeClass() (or similar) failed: a suite with its file and no test case.
        foreach ($xml->xpath('//testsuite[@file and @tests="0"]') ?: [] as $suite) {
            $failed[self::relative((string) $suite['file'], $root)] = true;
        }
        ksort($classes);

        return new self($classes, array_keys($failed));
    }

    public static function relative(string $path, string $root): string
    {
        $prefix = rtrim($root, '/') . '/';

        return str_starts_with($path, $prefix) ? substr($path, \strlen($prefix)) : $path;
    }
}
