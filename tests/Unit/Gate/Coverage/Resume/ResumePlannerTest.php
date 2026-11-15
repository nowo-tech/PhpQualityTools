<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\PhpSkeleton;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\ResumeDecision;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\ResumeOptions;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\ResumePlanner;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\ResumeState;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\Snapshot;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One fixture per fallback rule, plus the selection of a resume.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ResumePlannerTest extends TestCase
{
    use TempTreeTrait;

    private const TOOLCHAIN = ['php' => '8.2.0', 'phpunit' => '11.5.0', 'codeCoverage' => '11.0.0'];

    private const FILES = [
        'src/Calc.php' => "<?php\nnamespace App;\nclass Calc\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n}\n",
        'src/Base.php' => "<?php\nnamespace App;\nabstract class Base\n{\n    public function run(): int\n    {\n        return 1;\n    }\n}\n",
        'src/Child.php' => "<?php\nnamespace App;\nfinal class Child extends Base\n{\n}\n",
        'src/Grandchild.php' => "<?php\nnamespace App;\nclass Grandchild extends \\App\\Child\n{\n}\n",
        'src/Greets.php' => "<?php\nnamespace App;\ntrait Greets\n{\n    public function hello(): string\n    {\n        return 'hi';\n    }\n}\n",
        'src/Greeter.php' => "<?php\nnamespace App;\nfinal class Greeter\n{\n    use Greets;\n}\n",
        'src/Silent.php' => "<?php\nnamespace App;\nfinal class Silent\n{\n    public function __construct(private int \$x)\n    {\n    }\n}\n",
        'src/Kernel.php' => "<?php\nnamespace App;\nclass Kernel\n{\n    public function boot(): void\n    {\n        echo 1;\n    }\n}\n",
        'src/functions.php' => "<?php\nfunction helper(): int\n{\n    return 1;\n}\n",
        'src/view.twig' => '{{ x }}',
        'tests/CalcTest.php' => '<?php // runs Calc',
        'tests/ChildTest.php' => '<?php // runs Child',
        'tests/GreeterTest.php' => '<?php // runs Greeter',
        'tests/ProviderTest.php' => '<?php // a data provider builds new Calc()',
        'tests/SubprocessTest.php' => '<?php // runs bin/ in a subprocess',
        'tests/FailedTest.php' => '<?php // failed',
        'tests/Gate/RoutesTest.php' => '<?php // gate',
        'tests/bootstrap.php' => '<?php // bootstrap',
        'config/services.yaml' => 'services: ~',
        'composer.json' => '{}',
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTree(self::FILES);
    }

    public function testForcedOrWithoutAKeptRun(): void
    {
        self::assertSame(['forced (--force-full, FORCE_COVERAGE or COVERAGE_RESUME=0)'], $this->plan(['--force-full'])->reasons);
        self::assertSame(['no kept run'], (new ResumePlanner())->plan($this->options(), null, $this->snapshot(), self::TOOLCHAIN)->reasons);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function stateRules(): iterable
    {
        yield 'other root' => [['root' => '/elsewhere'], 'kept run from another project root (/elsewhere)'];
        yield 'php' => [['toolchain' => ['php' => '8.3.0'] + self::TOOLCHAIN], 'kept run made with PHP 8.3.0, now 8.2.0'];
        yield 'phpunit' => [['toolchain' => ['phpunit' => '10.5.0'] + self::TOOLCHAIN], 'kept run made with PHPUnit 10.5.0, now 11.5.0'];
        yield 'code coverage' => [['toolchain' => ['codeCoverage' => '10.1.0'] + self::TOOLCHAIN], 'kept run made with php-code-coverage 10.1.0, now 11.0.0'];
        yield 'green resume already used' => [['greenResumes' => 1], '1 resumed run(s) already passed since the last full run (--max-green-resumes=1)'];
        yield 'too many resumes' => [['resumes' => 5], '5 resumes chained since the last full run (--max-resumes=5)'];
    }

    /**
     * @param array<string, mixed> $state
     */
    #[DataProvider('stateRules')]
    public function testStateRulesForceTheFullSuite(array $state, string $reason): void
    {
        $this->write('tests/CalcTest.php', '<?php // changed');

        $decision = $this->plan([], $state);

        self::assertFalse($decision->resume);
        self::assertSame([$reason], $decision->reasons);
        self::assertSame('coverage: full', $decision->stamp());
    }

    /**
     * @return iterable<string, array{\Closure(self): void, string}>
     */
    public static function changeRules(): iterable
    {
        yield 'new source file' => [static fn (self $t) => $t->write('src/New.php', '<?php'), 'source file src/New.php added (a new, deleted or renamed source file)'];
        yield 'deleted source file' => [static fn (self $t) => $t->delete('src/Greeter.php'), 'source file src/Greeter.php deleted (a new, deleted or renamed source file)'];
        yield 'renamed source file' => [static function (self $t): void {
            rename($t->root . '/src/Calc.php', $t->root . '/src/Calculator.php');
        }, 'source file src/Calculator.php added (a new, deleted or renamed source file)'];
        yield 'configuration' => [static fn (self $t) => $t->write('config/services.yaml', 'services: { x: ~ }'), 'config/services.yaml changed: not a source or test file (configuration, dependencies, templates...)'];
        yield 'composer.json' => [static fn (self $t) => $t->write('composer.json', '{"a": 1}'), 'composer.json changed: not a source or test file (configuration, dependencies, templates...)'];
        yield 'new composer.lock' => [static fn (self $t) => $t->write('composer.lock', '{}'), 'composer.lock added: not a source or test file (configuration, dependencies, templates...)'];
        yield 'phpunit config deleted' => [static fn (self $t) => $t->delete('composer.json'), 'composer.json deleted: not a source or test file (configuration, dependencies, templates...)'];
        yield 'bootstrap' => [static fn (self $t) => $t->write('tests/bootstrap.php', '<?php // other'), 'tests/bootstrap.php changed: not a test class, may be shared by any test (bootstrap, helper, fixture)'];
        yield 'new fixture' => [static fn (self $t) => $t->write('tests/Fixtures/data.json', '{}'), 'tests/Fixtures/data.json added: not a test class, may be shared by any test (bootstrap, helper, fixture)'];
        yield 'not PHP' => [static fn (self $t) => $t->write('src/view.twig', '{{ y }}'), 'source file src/view.twig changed and is not PHP'];
        yield 'kernel' => [static fn (self $t) => $t->replace('src/Kernel.php', 'echo 1;', 'echo 2;'), 'src/Kernel.php changed: wiring or code shared by every test (--full-glob)'];
        yield 'signature' => [static fn (self $t) => $t->replace('src/Calc.php', 'int $b)', 'int $b = 0)'), 'src/Calc.php changed outside function bodies (signature, property, constant, attribute, import, inheritance)'];
        yield 'parent class' => [static fn (self $t) => $t->replace('src/Child.php', 'final class', 'class'), 'src/Child.php changed outside function bodies (signature, property, constant, attribute, import, inheritance)'];
        yield 'body without executable line' => [static fn (self $t) => $t->replace('src/Silent.php', "    {\n    }", "    {\n        \$this->x = 1;\n    }"), 'src/Silent.php: the function at lines 6-7 changed and had no executable line in the kept run, so no test is known to call it'];
    }

    /**
     * @param \Closure(self): void $change
     */
    #[DataProvider('changeRules')]
    public function testChangesThatForceTheFullSuite(\Closure $change, string $reason): void
    {
        $state = $this->state();
        $change($this);

        $decision = (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertFalse($decision->resume);
        self::assertContains($reason, $decision->reasons);
    }

    public function testMoreSourceFilesThanTheLimit(): void
    {
        $state = $this->state();
        $this->replace('src/Calc.php', '$a + $b', '$b + $a');
        $this->replace('src/Base.php', 'return 1;', 'return 2;');

        $decision = (new ResumePlanner())->plan($this->options(['--max-src-files=1']), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertSame(['2 source files changed (more than --max-src-files=1)'], $decision->reasons);
    }

    public function testASourceFileWithoutKeptAnalysis(): void
    {
        $state = $this->state(['src' => []]);
        $this->replace('src/Calc.php', '$a + $b', '$b + $a');

        $decision = (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertSame(['src/Calc.php changed and has no kept analysis'], $decision->reasons);
    }

    public function testTheReasonsAreCapped(): void
    {
        $state = $this->state();
        foreach (range(1, 7) as $i) {
            $this->write("config/{$i}.yaml", 'x');
        }

        self::assertCount(5, (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN)->reasons);
    }

    public function testNothingToRerun(): void
    {
        $decision = $this->plan([], ['failed' => []]);

        self::assertSame(['nothing to rerun: no failed test, no changed test or source file'], $decision->reasons);
    }

    public function testATestOnlyChangeRerunsTheFailedChangedNewAndGateTestFiles(): void
    {
        $state = $this->state();
        $this->write('tests/CalcTest.php', '<?php // fixed');
        $this->write('tests/NewTest.php', '<?php // new');
        $this->delete('tests/ChildTest.php');

        $decision = (new ResumePlanner())->plan($this->options(['--always=tests/Gate/*']), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertTrue($decision->resume);
        self::assertSame(['tests/CalcTest.php', 'tests/FailedTest.php', 'tests/Gate/RoutesTest.php', 'tests/NewTest.php'], $decision->testFiles);
        self::assertSame([], $decision->changedSrc);
        self::assertSame(['tests/ChildTest.php'], $decision->deletedTests);
        self::assertSame('coverage: resumed (4 tests, 0 files)', $decision->stamp());
    }

    public function testABodyChangeRerunsTheTestsThatExecutedTheFileTheNamingTestsAndTheUnattributedOnes(): void
    {
        $state = $this->state(['failed' => []]);
        $this->replace('src/Calc.php', '$a + $b', '$b + $a');

        $decision = (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertTrue($decision->resume, implode(', ', $decision->reasons));
        self::assertSame(['tests/CalcTest.php', 'tests/ProviderTest.php', 'tests/SubprocessTest.php'], $decision->testFiles);
        self::assertSame(['src/Calc.php'], $decision->changedSrc);
        self::assertSame('coverage: resumed (3 tests, 1 files)', $decision->stamp());

        $withoutNames = (new ResumePlanner())->plan($this->options(['--no-name-references']), $state, $this->snapshot(), self::TOOLCHAIN);
        self::assertSame(['tests/CalcTest.php', 'tests/SubprocessTest.php'], $withoutNames->testFiles);
    }

    public function testAParentOrTraitChangeAlsoRerunsTheTestsOfSubclassesAndTraitUsers(): void
    {
        $state = $this->state(['failed' => []]);
        $this->replace('src/Base.php', 'return 1;', 'return 2;');
        $this->replace('src/Greets.php', "'hi'", "'hello'");

        $decision = (new ResumePlanner())->plan($this->options(['--no-name-references']), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertTrue($decision->resume, implode(', ', $decision->reasons));
        // Base itself was executed by nobody: ChildTest (Child, Grandchild) and GreeterTest come from the hierarchy.
        self::assertSame(['tests/ChildTest.php', 'tests/GreeterTest.php', 'tests/SubprocessTest.php'], $decision->testFiles);
    }

    public function testAFileWithoutClassesHasNoNameReferences(): void
    {
        $state = $this->state(['failed' => []]);
        $this->replace('src/functions.php', 'return 1;', 'return 2;');

        $decision = (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertSame(['tests/CalcTest.php', 'tests/SubprocessTest.php'], $decision->testFiles);
    }

    public function testDeletedTestFilesAreNotRerun(): void
    {
        $state = $this->state();
        $this->delete('tests/FailedTest.php');

        $decision = (new ResumePlanner())->plan($this->options(), $state, $this->snapshot(), self::TOOLCHAIN);

        self::assertSame(['nothing to rerun: no failed test, no changed test or source file'], $decision->reasons);
    }

    public function testDecisionFactories(): void
    {
        self::assertSame(['x'], ResumeDecision::full(['x'])->reasons);
        self::assertSame(['a'], ResumeDecision::resume(['a'], [], [])->testFiles);
    }

    public function write(string $path, string $content): void
    {
        if (!is_dir(\dirname($this->root . '/' . $path))) {
            mkdir(\dirname($this->root . '/' . $path), 0o777, true);
        }
        file_put_contents($this->root . '/' . $path, $content);
    }

    public function delete(string $path): void
    {
        unlink($this->root . '/' . $path);
    }

    public function replace(string $path, string $search, string $replace): void
    {
        $content = (string) file_get_contents($this->root . '/' . $path);
        self::assertStringContainsString($search, $content);
        file_put_contents($this->root . '/' . $path, str_replace($search, $replace, $content));
    }

    /**
     * @param list<string>         $args
     * @param array<string, mixed> $state
     */
    private function plan(array $args = [], array $state = []): ResumeDecision
    {
        return (new ResumePlanner())->plan($this->options($args), $this->state($state), $this->snapshot(), self::TOOLCHAIN);
    }

    /**
     * @param list<string> $args
     */
    private function options(array $args = []): ResumeOptions
    {
        return ResumeOptions::parse($this->root, $args)[0];
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(): array
    {
        return Snapshot::take($this->root, null, ResumeOptions::DEFAULT_EXCLUDES, []);
    }

    /**
     * The kept run of the fixture: CalcTest executed Calc, ChildTest Child and Grandchild (Base::run is inherited
     * but, for the test, recorded on nobody), GreeterTest Greeter; SubprocessTest nothing; FailedTest failed.
     *
     * @param array<string, mixed> $overrides
     */
    private function state(array $overrides = []): ResumeState
    {
        $src = [];
        foreach (self::FILES as $path => $content) {
            if (str_starts_with($path, 'src/') && str_ends_with($path, '.php')) {
                $skeleton = PhpSkeleton::analyse($content);
                $src[$path] = [
                    'skeleton' => $skeleton->skeleton,
                    'bodies' => array_map(static fn (array $body): array => [$body[0], $body[1], $body[2], 'src/Silent.php' !== $path], $skeleton->bodies),
                    'declares' => $skeleton->declares,
                    'parents' => $skeleton->parents,
                ];
            }
        }
        $data = [
            'format' => ResumeState::FORMAT,
            'mode' => 'full',
            'status' => 'failed',
            'resumes' => 0,
            'greenResumes' => 0,
            'root' => $this->root,
            'toolchain' => self::TOOLCHAIN + ['driver' => 'pcov'],
            'snapshot' => $this->snapshot(),
            'src' => $src,
            'testFiles' => ['tests/CalcTest.php', 'tests/ChildTest.php', 'tests/GreeterTest.php', 'tests/ProviderTest.php', 'tests/SubprocessTest.php', 'tests/FailedTest.php'],
            'testClasses' => ['CalcTest' => 0, 'ChildTest' => 1, 'GreeterTest' => 2, 'ProviderTest' => 3, 'SubprocessTest' => 4, 'FailedTest' => 5],
            'index' => ['src/Calc.php' => [0], 'src/Child.php' => [1], 'src/Grandchild.php' => [1], 'src/Greeter.php' => [2], 'src/Silent.php' => [5], 'src/functions.php' => [0], 'tests/ProviderTest.php' => [3]],
            'failed' => ['tests/FailedTest.php'],
        ];

        return ResumeState::fromArray($overrides + $data);
    }
}
