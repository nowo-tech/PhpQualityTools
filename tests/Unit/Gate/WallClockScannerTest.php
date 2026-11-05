<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\WallClock\WallClockScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class WallClockScannerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function phpPositiveProvider(): iterable
    {
        yield 'new DateTime()' => ['$d = new DateTime();', 'new DateTime() reads the wall clock'];
        yield 'new \DateTimeImmutable without parentheses' => ['$d = new \DateTimeImmutable;', 'new DateTimeImmutable() reads the wall clock'];
        yield 'now with a time zone' => ['$d = new DateTimeImmutable(\'now\', new DateTimeZone(\'UTC\'));', "new DateTimeImmutable('now', new DateTimeZone('UTC')) reads"];
        yield 'tomorrow' => ['$d = new \DateTimeImmutable("tomorrow");', 'new DateTimeImmutable("tomorrow") reads'];
        yield 'yesterday midnight' => ['$d = new DateTime(\'yesterday midnight\');', "new DateTime('yesterday midnight') reads"];
        yield 'next monday' => ['$d = new DateTimeImmutable(\'next monday\');', "new DateTimeImmutable('next monday') reads"];
        yield 'plus one day' => ['$d = new DateTimeImmutable(\'+1 day\');', "new DateTimeImmutable('+1 day') reads"];
        yield 'three weeks ago' => ['$d = new DateTimeImmutable(\'3 weeks ago\');', "new DateTimeImmutable('3 weeks ago') reads"];
        yield 'empty string' => ['$d = new DateTime(\'\');', "new DateTime('') reads"];
        yield 'Carbon' => ['$d = new Carbon\Carbon(\'today\');', "new Carbon\\Carbon('today') reads"];
        yield 'Carbon::now()' => ['$d = Carbon::now();', 'Carbon::now() reads the wall clock'];
        yield 'CarbonImmutable::tomorrow()' => ['$d = \Carbon\CarbonImmutable::tomorrow();', 'Carbon\CarbonImmutable::tomorrow() reads'];
        yield 'time()' => ['$t = time();', 'time() reads the wall clock'];
        yield '\time()' => ['$t = \time() + 60;', 'time() reads the wall clock'];
        yield 'date() without timestamp' => ['$y = date(\'Y\');', "date('Y') reads the wall clock"];
        yield 'gmdate() without timestamp' => ['$y = gmdate(\'Y-m-d\');', "gmdate('Y-m-d') reads"];
        yield 'date_create()' => ['$d = date_create();', 'date_create() reads'];
        yield 'date_create_immutable(today)' => ['$d = date_create_immutable(\'today\');', "date_create_immutable('today') reads"];
        yield 'strtotime relative' => ['$t = strtotime(\'tomorrow\');', "strtotime('tomorrow') reads"];
    }

    #[DataProvider('phpPositiveProvider')]
    public function testPhpPositive(string $code, string $expected): void
    {
        $findings = (new WallClockScanner())->scanPhp("<?php\n\n" . $code . "\n");

        self::assertCount(1, $findings, $code);
        self::assertSame(3, $findings[0][0]);
        self::assertStringContainsString($expected, $findings[0][1]);
        self::assertStringContainsString('freeze the clock: MockClock / ClockSensitiveTrait::mockTime()', $findings[0][1]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function phpNegativeProvider(): iterable
    {
        yield 'absolute date' => ['$d = new DateTimeImmutable(\'2026-01-15 10:00:00\');'];
        yield 'absolute date with relative words' => ['$d = new DateTimeImmutable(\'2026-01-15 midnight\');'];
        yield 'relative phrase with a year' => ['$d = new DateTimeImmutable(\'first monday of january 2026\');'];
        yield 'timestamp' => ['$d = new DateTimeImmutable(\'@1700000000\');'];
        yield 'variable argument' => ['$d = new DateTimeImmutable($when);'];
        yield 'concatenated argument' => ['$d = new DateTimeImmutable(\'2026-01-\' . $day);'];
        yield 'other class' => ['$d = new DateTimeZone(\'UTC\'); $p = new DatePoint();'];
        yield 'date with timestamp' => ['$y = date(\'Y\', 0);'];
        yield 'strtotime with base' => ['$t = strtotime(\'+1 day\', 1700000000);'];
        yield 'strtotime absolute' => ['$t = strtotime(\'2026-01-01\');'];
        yield 'date_create absolute' => ['$d = date_create(\'2026-01-01\');'];
        yield 'method named time' => ['$t = $clock->time(); $u = $clock?->time(); $v = Clock::time();'];
        yield 'function declaration' => ['function time(): int { return 1; }'];
        yield 'namespaced function' => ['$t = Foo\time();'];
        yield 'constant and named argument' => ['$t = time; f(time: 3);'];
        yield 'string and comment' => ["\$s = 'new DateTime()'; // time() and new DateTime()\n/* date('Y') */"];
        yield 'Carbon with a fixed date' => ['$d = Carbon::parse(\'2026-01-01\'); $e = Carbon::create(2026, 1, 1);'];
        yield 'Carbon class constant' => ['$c = Carbon::class;'];
        yield 'nested call arguments' => ['$y = date(\'Y\', mktime(0, 0, 0, 1, [1][0], 2026));'];
        yield 'unclosed call at end of file' => ['$y = date('];
        yield 'new with a variable class' => ['$d = new $class();'];
    }

    #[DataProvider('phpNegativeProvider')]
    public function testPhpNegative(string $code): void
    {
        self::assertSame([], (new WallClockScanner())->scanPhp("<?php\n" . $code . "\n"), $code);
    }

    public function testFrozenClockMarkersSkipTheFile(): void
    {
        $scanner = new WallClockScanner();
        foreach ([
            'use Symfony\Component\Clock\Test\ClockSensitiveTrait;',
            '$clock = new MockClock(\'2026-01-01\');',
            'self::mockTime(\'2026-01-01\');',
            '#[Group(\'time-sensitive\')]',
            'ClockMock::register(self::class);',
            'Carbon::setTestNow(\'2026-01-01\');',
            'Clock::set(new MockClock());',
        ] as $marker) {
            self::assertSame([], $scanner->scanPhp("<?php\n{$marker}\n\$d = new DateTimeImmutable();\n"), $marker);
        }

        $custom = new WallClockScanner(['/\$this->freezeTime\(/']);
        self::assertSame([], $custom->scanPhp("<?php\n\$this->freezeTime();\n\$t = time();\n"));
        self::assertCount(1, $custom->scanPhp("<?php\n\$t = time();\n"));
    }

    public function testInlineOptOut(): void
    {
        $source = <<<'PHP'
            <?php
            $a = time(); // @wallclock-ignore measuring elapsed time
            // @wallclock-ignore: the expectation is relative too
            $b = new DateTimeImmutable('tomorrow');
            $c = time();
            /*
             * @wallclock-ignore
             */
            $d = date('Y');
            $e = time();// @wallclock-ignore
            $f = time();
            PHP;

        self::assertSame([5, 11], array_column((new WallClockScanner())->scanPhp($source), 0));
    }

    public function testJs(): void
    {
        $scanner = new WallClockScanner();
        $source = <<<'TS'
            const fixed = new Date('2026-01-01T00:00:00Z');
            const now = new Date();
            const later = new  Date( ) ;
            // const old = new Date();
            /* new Date() */
             * new Date()
            const ok = new Date(); // @wallclock-ignore
            // @wallclock-ignore elapsed time only
            const ok2 = new Date();
            const x = 1; // @wallclock-ignore unrelated
            const bad = new Date();
            const t = Date.now();
            TS;

        $findings = $scanner->scanJs($source);
        self::assertSame([2, 3, 11], array_column($findings, 0));
        self::assertStringContainsString('new Date() reads the wall clock (use fake timers', $findings[0][1]);

        foreach (['vi.useFakeTimers();', 'jest.useFakeTimers()', 'vi.setSystemTime(0)', "import MockDate from 'mockdate';", 'await page.clock.install({ time });', "import FakeTimers from '@sinonjs/fake-timers';"] as $marker) {
            self::assertSame([], $scanner->scanJs($marker . "\nconst now = new Date();\n"), $marker);
        }
    }

    public function testIsRelativeDate(): void
    {
        foreach (['', ' now ', 'Today', 'tomorrow noon', 'last day of next month', 'this week', '-2 weeks', '+90 minutes', '10 days ago', 'Friday'] as $value) {
            self::assertTrue(WallClockScanner::isRelativeDate($value), $value);
        }
        foreach (['2026-01-01', '01/15/2026', '@0', 'next monday 2026', 'UTC', 'Europe/Madrid', 'snowy', '2026-01-01 +1 day'] as $value) {
            self::assertFalse(WallClockScanner::isRelativeDate($value), $value);
        }
    }
}
