<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * What {@see ResumePlanner} decided: the full suite (with the reasons), or a resume with the test files to rerun.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class ResumeDecision
{
    /**
     * @param list<string> $reasons      why the full suite runs (empty on a resume)
     * @param list<string> $testFiles    test files to rerun, sorted
     * @param list<string> $changedSrc   source files changed since the kept run (their old data is dropped)
     * @param list<string> $deletedTests test files deleted since the kept run (their old data is dropped)
     */
    private function __construct(
        public bool $resume,
        public array $reasons,
        public array $testFiles = [],
        public array $changedSrc = [],
        public array $deletedTests = [],
    ) {
    }

    /**
     * @param list<string> $reasons
     */
    public static function full(array $reasons): self
    {
        return new self(false, $reasons);
    }

    /**
     * @param list<string> $testFiles
     * @param list<string> $changedSrc
     * @param list<string> $deletedTests
     */
    public static function resume(array $testFiles, array $changedSrc, array $deletedTests): self
    {
        return new self(true, [], $testFiles, $changedSrc, $deletedTests);
    }

    /**
     * The line stamped in the release-check OK line and the tag message.
     */
    public function stamp(): string
    {
        return $this->resume
            ? \sprintf('coverage: resumed (%d test files, %d source files)', \count($this->testFiles), \count($this->changedSrc))
            : 'coverage: full';
    }
}
