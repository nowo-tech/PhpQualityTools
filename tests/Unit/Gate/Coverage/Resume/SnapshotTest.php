<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\JunitTests;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\Snapshot;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class SnapshotTest extends TestCase
{
    use TempTreeTrait;

    public function testWalksTheRootWithoutExcludedPathsAndAlwaysHashesTheInputs(): void
    {
        $root = $this->makeTree([
            'src/A.php' => 'a',
            'tests/ATest.php' => 't',
            'vendor/lib/X.php' => 'x',
            'vendor/composer/installed.json' => '{}',
            'var/cache/c' => 'c',
            'notes.md' => 'n',
            'config/deep/services.yaml' => 's',
        ]);
        symlink($root . '/src', $root . '/linked');

        $snapshot = Snapshot::take($root, null, ['vendor/', 'var/', '*.md'], ['vendor/composer/installed.json', 'config', 'missing.json']);

        self::assertSame(['config/deep/services.yaml', 'src/A.php', 'tests/ATest.php', 'vendor/composer/installed.json'], array_keys($snapshot));
        self::assertSame(sha1('a'), $snapshot['src/A.php']);
        unlink($root . '/linked');
    }

    public function testAFileListIsFilteredAndNormalised(): void
    {
        $root = $this->makeTree(['src/A.php' => 'a', 'var/x' => 'x']);

        $snapshot = Snapshot::take($root, ['./src/A.php', ' var/x ', 'gone.php', ''], ['var/'], []);

        self::assertSame(['src/A.php' => sha1('a')], $snapshot);
    }

    public function testDiff(): void
    {
        $diff = Snapshot::diff(['a' => '1', 'b' => '2', 'c' => '3'], ['a' => '1', 'b' => '9', 'd' => '4']);

        self::assertSame(['added' => ['d'], 'deleted' => ['c'], 'modified' => ['b']], $diff);
    }

    public function testExcludePatterns(): void
    {
        self::assertTrue(Snapshot::isExcluded('var/cache/x', ['var/']));
        self::assertFalse(Snapshot::isExcluded('variable.php', ['var/']));
        self::assertTrue(Snapshot::isExcluded('docs/a.md', ['*.md']));
        self::assertTrue(Snapshot::isExcluded('deep/.phpunit.result.cache', ['.phpunit.result.cache']));
        self::assertFalse(Snapshot::isExcluded('src/A.php', []));
    }

    public function testJunitClassesFailuresAndRelativePaths(): void
    {
        $root = $this->makeTree(['junit.xml' => <<<'XML'
            <?xml version="1.0"?>
            <testsuites><testsuite name="all">
              <testsuite name="App\Tests\ATest" file="/app/tests/ATest.php">
                <testcase name="a" class="App\Tests\ATest" file="/app/tests/ATest.php"/>
                <testcase name="b" class="App\Tests\ATest" file="/app/tests/ATest.php"><failure>no</failure></testcase>
              </testsuite>
              <testcase name="c" class="App\Tests\BTest" file="/app/tests/BTest.php"><error>boom</error></testcase>
              <testcase name="d" class="App\Tests\CTest" file="/elsewhere/CTest.php"/>
              <testcase name="x.phpt" file="/app/tests/x.phpt"/>
            </testsuite></testsuites>
            XML]);

        $junit = JunitTests::read($root . '/junit.xml', '/app/');

        self::assertSame(['App\Tests\ATest' => 'tests/ATest.php', 'App\Tests\BTest' => 'tests/BTest.php', 'App\Tests\CTest' => '/elsewhere/CTest.php'], $junit->classes);
        self::assertSame(['tests/ATest.php', 'tests/BTest.php'], $junit->failed);
    }

    public function testJunitMissingOrInvalid(): void
    {
        $root = $this->makeTree(['bad.xml' => 'not xml']);

        foreach (['/missing.xml', '/bad.xml'] as $file) {
            try {
                JunitTests::read($root . $file, $root);
                self::fail('expected an exception');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('JUnit log not found or not XML', $e->getMessage());
            }
        }
    }
}
