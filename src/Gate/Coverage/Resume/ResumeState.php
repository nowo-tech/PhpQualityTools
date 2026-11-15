<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * The kept run (`<state dir>/state.json`, next to its `coverage.cov`): what the next run is compared with.
 *
 * - `snapshot`: content hash of every input file when the kept run started;
 * - `src`: per source file, its {@see PhpSkeleton} (skeleton hash; body line ranges, hashes and whether the kept
 *   run saw an executable line in each; declared and parent short names);
 * - `testFiles` / `testClasses` / `index`: which test files executed each source file (from the per-test data);
 * - `failed`: test files with a failure or an error;
 * - `mode`, `status`, `resumes`, `greenResumes`: the marker policy — a full run resets the counters, each resume
 *   adds one (and one green resume when it passed), so the planner can force the full suite.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class ResumeState
{
    public const FORMAT = 1;

    /**
     * @param array<string, string>                                                                                                                          $toolchain   php, phpunit, codeCoverage, driver
     * @param array<string, string>                                                                                                                          $snapshot
     * @param array<string, array{skeleton: string, bodies: list<array{0: int, 1: int, 2: string, 3: bool}>, declares: list<string>, parents: list<string>}> $src
     * @param list<string>                                                                                                                                   $testFiles
     * @param array<string, int>                                                                                                                             $testClasses test class => index in $testFiles
     * @param array<string, list<int>>                                                                                                                       $index       source file => indexes in $testFiles
     * @param list<string>                                                                                                                                   $failed
     */
    public function __construct(
        public string $mode,
        public string $status,
        public int $resumes,
        public int $greenResumes,
        public string $root,
        public string $commit,
        public string $fullCommit,
        public string $createdAt,
        public array $toolchain,
        public array $snapshot,
        public array $src,
        public array $testFiles,
        public array $testClasses,
        public array $index,
        public array $failed,
        public string $stamp,
    ) {
    }

    /**
     * @param array<string, mixed> $data the decoded state.json
     *
     * @throws \UnexpectedValueException when it is not a state of this format
     */
    public static function fromArray(array $data): self
    {
        if (self::FORMAT !== ($data['format'] ?? null)) {
            throw new \UnexpectedValueException('kept state of another format');
        }
        $string = static fn (string $key): string => \is_string($data[$key] ?? null) ? $data[$key] : '';
        $int = static fn (string $key): int => \is_int($data[$key] ?? null) ? $data[$key] : 0;
        $array = static fn (string $key): array => \is_array($data[$key] ?? null) ? $data[$key] : [];

        /* @phpstan-ignore-next-line argument.type (trusted file written by toArray()) */
        return new self($string('mode'), $string('status'), $int('resumes'), $int('greenResumes'), $string('root'), $string('commit'), $string('fullCommit'), $string('createdAt'), $array('toolchain'), $array('snapshot'), $array('src'), $array('testFiles'), $array('testClasses'), $array('index'), $array('failed'), $string('stamp'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['format' => self::FORMAT] + get_object_vars($this);
    }

    /**
     * Test files that executed at least one line of the source file.
     *
     * @return list<string>
     */
    public function testsOf(string $srcFile): array
    {
        return array_map(fn (int $i): string => $this->testFiles[$i], $this->index[$srcFile] ?? []);
    }

    /**
     * Test files with no executed line at all in the kept run (code reached only in a subprocess, or nothing):
     * nothing tells what they depend on.
     *
     * @return list<string>
     */
    public function unattributedTests(): array
    {
        $seen = [];
        foreach ($this->index as $indexes) {
            foreach ($indexes as $i) {
                $seen[$i] = true;
            }
        }

        return array_values(array_diff_key($this->testFiles, $seen));
    }

    /**
     * Test classes whose file is one of these.
     *
     * @param list<string> $files
     *
     * @return list<string>
     */
    public function classesOf(array $files): array
    {
        $wanted = array_flip($files);

        return array_map(strval(...), array_keys(array_filter($this->testClasses, fn (int $i): bool => isset($wanted[$this->testFiles[$i]]))));
    }
}
