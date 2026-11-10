<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerBaseline;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryCommand;
use PHPUnit\Framework\TestCase;

/**
 * `check-module-boundaries` with a `layers` section: enforce / warn modes, the baseline ratchet, and the proof that
 * maps without `layers` behave exactly as before 1.12.0.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryLayersCommandTest extends TestCase
{
    use TempTreeTrait;

    private const BASELINE = 'config/quality/module-boundaries-baseline.txt';

    /**
     * Backward compatibility: the 1.11.0 Beacon map on a tree full of layer-rule findings prints exactly what 1.11.0
     * printed (strings unchanged since 1.2.0), and the new flags are refused without a "layers" section.
     */
    public function testMapsWithoutLayersBehaveAsBefore(): void
    {
        $tree = [
            ...ModuleBoundaryCheckerTest::beaconTree(),
            'config/quality/module-boundaries.yaml' => (string) file_get_contents(ModuleBoundaryCheckerTest::BEACON_CONFIG),
            'src/Ops/Domain/Thing.php' => "<?php\nnamespace App\\Ops\\Domain;\nuse Symfony\\Component\\Uid\\Uuid;\nuse App\\Ops\\UI\\Ctl;\nfinal class Thing { public function f(\$em): void { \$em->flush(); } }\n",
            'src/Ops/UI/Ctl.php' => "<?php\nnamespace App\\Ops\\UI;\nuse Doctrine\\ORM\\EntityManagerInterface;\nuse App\\Ops\\Infrastructure\\ThingRepository;\nfinal class Ctl {}\n",
            self::BASELINE => "not|a baseline\n",
        ];
        $root = $this->makeTree($tree);

        self::assertSame(
            [0, "OK: module boundaries (7 module(s), 19 PHP file(s) scanned, 3 edge rule(s), 18 extra rule(s)).\n", ''],
            $this->execute([], $root),
        );

        $root = $this->makeTree([...$tree, 'src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse App\\Ops\\Messenger\\MessengerQueueHealth;\n"]);
        self::assertSame([
            1,
            '',
            "error: src/Shared/Util/Clock.php:3 imports App\\Ops\\Messenger\\MessengerQueueHealth (Shared -> Ops is forbidden): Shared/Project must not depend on App\\Ops or App\\Ingest (see spec 131).\n"
            . "Module boundaries: 1 violation(s).\n",
        ], $this->execute([], $root));

        foreach (['--baseline=x.txt', '--strict-baseline', '--update-baseline', '--allow-grow'] as $flag) {
            [$code, $out, $err] = $this->execute([$flag], $root);
            self::assertSame(2, $code, $flag);
            self::assertSame('', $out);
            self::assertStringContainsString('need a "layers" section in ' . $root . '/config/quality/module-boundaries.yaml', $err);
        }
        [$code, , $err] = $this->execute(['--baseline='], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown argument --baseline=', $err);
    }

    public function testEnforceFailsAndWarnReports(): void
    {
        $root = $this->layeredRoot();

        [$code, $out, $err] = $this->execute([], $root);

        self::assertSame(1, $code);
        self::assertSame(4, substr_count($out, 'warning: '));
        self::assertStringContainsString("warning: src/Billing/Entity/Invoice.php:4 references Doctrine\\ORM\\Mapping (Billing Domain, layer rule domain-framework-free): Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).\n", $out);
        self::assertStringEndsWith("Layers: 3 context(s), 10 file(s), 7 rule(s): 11 error(s), 4 warning(s), 0 baselined, 0 stale baseline entries.\n", $out);
        self::assertSame(11, substr_count($err, 'error: '));
        self::assertStringContainsString("error: src/Uptime/Legacy/Helper.php:4 calls ->flush() (Uptime (no layer), layer rule flush-in-application): flush() belongs to the Application (transaction per use case) or Infrastructure layer.\n", $err);
        self::assertStringEndsWith("Module boundaries: 11 violation(s).\n", $err);
    }

    public function testBaselineOnlyShrinks(): void
    {
        $root = $this->layeredRoot();
        $baseline = $root . '/' . self::BASELINE;

        // 1. Generate: a missing baseline is created with every finding (enforce and warn contexts).
        self::assertSame([0, "Baseline created: {$baseline} (15 entries; 15 added, 0 removed).\n", ''], $this->execute(['--update-baseline'], $root));
        $content = (string) file_get_contents($baseline);
        self::assertStringStartsWith(LayerBaseline::HEADER, $content);
        self::assertStringContainsString("\nflush-in-application|src/Uptime/Legacy/Helper.php|->flush()\n", $content);
        self::assertSame(15, array_sum(LayerBaseline::load($baseline)));

        // 2. Every finding is baselined: the run passes.
        [$code, $out, $err] = $this->execute([], $root);
        self::assertSame(0, $code, $err);
        self::assertSame(
            "Layers: 3 context(s), 10 file(s), 7 rule(s): 0 error(s), 0 warning(s), 15 baselined, 0 stale baseline entries.\n"
            . "OK: module boundaries (4 module(s), 11 PHP file(s) scanned, 1 edge rule(s), 0 extra rule(s)).\n",
            $out,
        );

        // 3. A fixed finding leaves a stale entry: reported, and fatal with --strict-baseline.
        file_put_contents($root . '/src/Uptime/Legacy/Helper.php', "<?php\nnamespace App\\Uptime\\Legacy;\n");
        $stale = 'baseline entry no longer occurs (remove it, or run --update-baseline): flush-in-application|src/Uptime/Legacy/Helper.php|->flush()';
        [$code, $out] = $this->execute([], $root);
        self::assertSame(0, $code);
        self::assertStringContainsString("warning: {$stale}\n", $out);
        self::assertStringContainsString('14 baselined, 1 stale baseline entry.', $out);
        [$code, $out, $err] = $this->execute(['--strict-baseline'], $root);
        self::assertSame(1, $code);
        self::assertSame("error: {$stale}\nModule boundaries: 1 violation(s).\n", $err);
        self::assertStringContainsString('1 error(s), 0 warning(s), 14 baselined, 1 stale baseline entry.', $out);

        // 4. New findings fail, and --update-baseline refuses to add them (a key counts once per occurrence).
        file_put_contents($root . '/src/Uptime/Legacy/Two.php', "<?php\nnamespace App\\Uptime\\Legacy;\nfunction f(\$a, \$b): void { \$a->flush(); \$b->flush(); }\n");
        [$code, , $err] = $this->execute([], $root);
        self::assertSame(1, $code);
        self::assertSame(2, substr_count($err, 'error: src/Uptime/Legacy/Two.php:3 calls ->flush()'));
        [$code, $out, $err] = $this->execute(['--update-baseline'], $root);
        self::assertSame([1, ''], [$code, $out]);
        self::assertStringStartsWith("error: refusing to add 2 entries to the baseline {$baseline} (fix them, or pass --allow-grow):\n  src/Uptime/Legacy/Two.php:3 calls ->flush()", $err);
        self::assertSame($content, file_get_contents($baseline));

        // 5. --allow-grow accepts them (and drops the stale entry).
        self::assertSame(
            [0, "Baseline updated: {$baseline} (16 entries; 2 added, 1 removed).\n", ''],
            $this->execute(['--update-baseline', '--allow-grow'], $root),
        );
        self::assertSame(2, substr_count((string) file_get_contents($baseline), "flush-in-application|src/Uptime/Legacy/Two.php|->flush()\n"));

        // 6. Shrinking needs no flag; an explicit --baseline (relative to the working directory) is honoured.
        unlink($root . '/src/Uptime/Legacy/Two.php');
        self::assertSame(
            [0, "Baseline updated: {$baseline} (14 entries; 0 added, 2 removed).\n", ''],
            $this->execute(['--update-baseline', '--baseline=' . self::BASELINE], $root),
        );
        self::assertSame(0, $this->execute(['--strict-baseline'], $root)[0]);
        self::assertSame(1, $this->execute(['--baseline=other.txt'], $root)[0]);
    }

    public function testBaselineErrors(): void
    {
        $root = $this->layeredRoot();

        [$code, , $err] = $this->execute(['--allow-grow'], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('--allow-grow only applies to --update-baseline.', $err);

        file_put_contents($root . '/bad.txt', "# comment\n\nrule-only\n");
        [$code, , $err] = $this->execute(['--baseline=bad.txt'], $root);
        self::assertSame(2, $code);
        self::assertSame("ERROR: {$root}/bad.txt:3 is not a \"rule|file|subject\" baseline entry.\n", $err);

        [$code, , $err] = $this->execute(['--baseline=src'], $root);
        self::assertSame(2, $code);
        self::assertSame("ERROR: cannot read baseline {$root}/src\n", $err);

        [$code, , $err] = $this->execute(['--update-baseline', '--baseline=missing/dir.txt'], $root);
        self::assertSame(2, $code);
        self::assertSame("ERROR: baseline directory not found: {$root}/missing/dir.txt\n", $err);

        $outside = $this->makeTree(['keep.txt' => '']);
        [$code, , $err] = $this->execute(['--update-baseline', '--baseline=' . $outside . '/baseline.txt'], $root);
        self::assertSame(2, $code);
        self::assertSame("ERROR: refusing to write the baseline outside the project root: {$outside}/baseline.txt\n", $err);
        self::assertFileDoesNotExist($outside . '/baseline.txt');

        file_put_contents($root . '/real.txt', '');
        symlink($root . '/real.txt', $root . '/link.txt');
        [$code, , $err] = $this->execute(['--update-baseline', '--allow-grow', '--baseline=link.txt'], $root);
        self::assertSame(2, $code);
        self::assertSame("ERROR: refusing to write the baseline through a symbolic link: {$root}/link.txt\n", $err);
        self::assertSame('', file_get_contents($root . '/real.txt'));
    }

    private function layeredRoot(): string
    {
        return $this->makeTree([
            ...LayerCheckerTest::layeredTree(),
            'config/quality/module-boundaries.yaml' => (string) file_get_contents(LayerCheckerTest::LAYERS_CONFIG),
        ]);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, string $cwd): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $code = (new ModuleBoundaryCommand($stdout, $stderr, $cwd))->run($args);

        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
