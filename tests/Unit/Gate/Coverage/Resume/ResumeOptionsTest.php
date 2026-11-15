<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\ResumeOptions;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ResumeOptionsTest extends TestCase
{
    use TempTreeTrait;

    public function testDefaults(): void
    {
        [$options, $rest] = ResumeOptions::parse('/app', []);

        self::assertSame('var/coverage/resume', $options->stateDir);
        self::assertSame(['src'], $options->srcDirs);
        self::assertSame(['tests'], $options->testDirs);
        self::assertSame(10, $options->maxSrcFiles);
        self::assertSame(1, $options->maxGreenResumes);
        self::assertSame(5, $options->maxResumes);
        self::assertSame(ResumeOptions::DEFAULT_FULL_GLOBS, $options->fullGlobs);
        self::assertContains('var/coverage/resume/', $options->excludes);
        self::assertSame(ResumeOptions::DEFAULT_INPUTS, $options->inputs);
        self::assertNull($options->files);
        self::assertTrue($options->nameReferences);
        self::assertFalse($options->forceFull);
        self::assertSame([], $rest);
        self::assertSame('/app/var/coverage/resume/state.json', $options->statePath('state.json'));
    }

    public function testValuesListsFlagsAndLeftovers(): void
    {
        $root = $this->makeTree(['files.txt' => "src/A.php\n\n tests/ATest.php \n"]);

        [$options, $rest] = ResumeOptions::parse($root, [
            '--state=/abs/state/', '--src=./lib/', '--src=app', '--tests=spec', '--test-suffix=Spec.php',
            '--max-src-files=3', '--max-green-resumes=0', '--max-resumes=2', '--always=spec/Gate/*', '--full-glob=lib/Wiring/*',
            '--full-glob=', '--exclude=docs/', '--input=.env.ci', '--files-from=files.txt', '--no-name-references', '--force-full',
            '--coverage=x.cov',
        ]);

        self::assertSame('/abs/state', $options->stateDir);
        self::assertSame('/abs/state/pending.json', $options->statePath('pending.json'));
        self::assertSame(['lib', 'app'], $options->srcDirs);
        self::assertSame(['spec'], $options->testDirs);
        self::assertSame([3, 0, 2], [$options->maxSrcFiles, $options->maxGreenResumes, $options->maxResumes]);
        self::assertSame(['lib/Wiring/*'], $options->fullGlobs);
        self::assertContains('docs/', $options->excludes);
        self::assertContains('/abs/state/', $options->excludes);
        self::assertContains('.env.ci', $options->inputs);
        self::assertSame(['src/A.php', 'tests/ATest.php'], $options->files);
        self::assertFalse($options->nameReferences);
        self::assertTrue($options->forceFull);
        self::assertSame(['coverage' => 'x.cov'], $rest);

        self::assertTrue($options->isSource('lib/A.php'));
        self::assertFalse($options->isSource('library/A.php'));
        self::assertTrue($options->isTestFile('spec/ASpec.php'));
        self::assertFalse($options->isTestFile('spec/Helper.php'));
        self::assertTrue($options->isTestDir('spec/Helper.php'));
        self::assertTrue($options->isAlways('spec/Gate/RoutesSpec.php'));
        self::assertFalse($options->isAlways('spec/Unit/ASpec.php'));
        self::assertTrue($options->isFullGlob('lib/Wiring/Kernel.php'));
        self::assertFalse($options->isFullGlob('lib/Domain/A.php'));
    }

    public function testDefaultFullGlobs(): void
    {
        [$options] = ResumeOptions::parse('/app', []);

        foreach (['src/Kernel.php', 'src/AcmeBundle.php', 'src/DependencyInjection/Configuration.php', 'src/Billing/DependencyInjection/X.php', 'src/Migrations/Version1.php', 'src/DataFixtures/AppFixtures.php', 'src/Resources/config/services.php'] as $path) {
            self::assertTrue($options->isFullGlob($path), $path);
        }
        self::assertFalse($options->isFullGlob('src/Billing/Invoice.php'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function forcingEnvironments(): iterable
    {
        yield 'COVERAGE_RESUME=0' => [['COVERAGE_RESUME' => '0']];
        yield 'COVERAGE_RESUME=off' => [['COVERAGE_RESUME' => 'OFF']];
        yield 'FORCE_COVERAGE=1' => [['FORCE_COVERAGE' => '1']];
    }

    /**
     * @param array<string, string> $env
     */
    #[DataProvider('forcingEnvironments')]
    public function testTheEnvironmentForcesTheFullSuite(array $env): void
    {
        self::assertTrue(ResumeOptions::parse('/app', [], $env)[0]->forceFull);
        self::assertFalse(ResumeOptions::parse('/app', [], ['COVERAGE_RESUME' => '1', 'FORCE_COVERAGE' => ''])[0]->forceFull);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badArguments(): iterable
    {
        yield 'positional' => ['plan', 'Unknown argument plan'];
        yield 'not a number' => ['--max-src-files=many', '--max-src-files must be a whole number'];
        yield 'missing list' => ['--files-from=nope.txt', '--files-from file not found: nope.txt'];
    }

    #[DataProvider('badArguments')]
    public function testBadArguments(string $argument, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        ResumeOptions::parse('/app', [$argument]);
    }
}
