<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\DemoLock\ConstraintMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintMatcher::class)]
final class ConstraintMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function cases(): iterable
    {
        // caret
        yield 'caret same major' => ['1.5.2', '^1.5', true];
        yield 'caret below' => ['1.4.9', '^1.5', false];
        yield 'caret next major' => ['2.0.0', '^1.5', false];
        yield 'caret zero major keeps the minor' => ['0.3.9', '^0.3', true];
        yield 'caret zero major next minor' => ['0.4.0', '^0.3', false];
        yield 'caret zero major patch bound' => ['0.0.3', '^0.0.3', true];
        yield 'caret zero major patch bound next' => ['0.0.4', '^0.0.3', false];
        yield 'caret 0.0 keeps minor zero' => ['0.0.9', '^0.0', true];
        yield 'caret 0.0 next minor' => ['0.1.0', '^0.0', false];
        yield 'caret bare zero' => ['0.9.9', '^0', true];
        yield 'caret bare zero next' => ['1.0.0', '^0', false];
        yield 'caret with v prefix and space' => ['v1.5.2', '^ 1.5', true];
        // tilde
        yield 'tilde two parts' => ['1.9.0', '~1.2', true];
        yield 'tilde two parts next major' => ['2.0.0', '~1.2', false];
        yield 'tilde three parts' => ['1.2.9', '~1.2.3', true];
        yield 'tilde three parts next minor' => ['1.3.0', '~1.2.3', false];
        yield 'tilde below' => ['1.2.2', '~1.2.3', false];
        // comparison
        yield 'gte' => ['2.1.0', '>=2.0', true];
        yield 'gte spaced' => ['2.1.0', '>= 2.0', true];
        yield 'gte below' => ['1.9.9', '>=2.0', false];
        yield 'gt' => ['2.0.1', '>2.0.0', true];
        yield 'gt equal' => ['2.0.0', '>2.0.0', false];
        yield 'lt' => ['1.9.9', '<2.0', true];
        yield 'lt equal' => ['2.0.0', '<2.0', false];
        yield 'lte equal' => ['2.0.0', '<=2.0.0', true];
        yield 'lte above' => ['2.0.1', '<=2.0.0', false];
        yield 'range with comma' => ['1.5.0', '>=1.2,<2.0', true];
        yield 'range with space out' => ['2.0.0', '>=1.2 <2.0', false];
        // exact and wildcards
        yield 'exact' => ['1.2.3', '1.2.3', true];
        yield 'exact with equals' => ['1.2.3', '=1.2.3', true];
        yield 'exact other' => ['1.2.4', '1.2.3', false];
        yield 'wildcard minor' => ['1.2.9', '1.2.*', true];
        yield 'wildcard minor next' => ['1.3.0', '1.2.*', false];
        yield 'wildcard major' => ['1.9.0', '1.*', true];
        yield 'wildcard major next' => ['2.0.0', '1.x', false];
        // alternatives
        yield 'or first' => ['1.5.0', '^1.0 || ^2.0', true];
        yield 'or second' => ['2.5.0', '^1.0 || ^2.0', true];
        yield 'or single pipe' => ['2.5.0', '^1.0 | ^2.0', true];
        yield 'or none' => ['3.0.0', '^1.0 || ^2.0', false];
        // always satisfied
        yield 'dev version' => ['dev-main', '^9.0', true];
        yield 'star' => ['0.0.1', '*', true];
        yield 'at dev' => ['0.0.1', '@dev', true];
        yield 'empty' => ['0.0.1', '', true];
        yield 'stability flag' => ['1.0.0', '^1.0@dev', true];
        yield 'suffix and metadata ignored' => ['1.5.0-beta1+build', '^1.5', true];
        yield 'non numeric segment counts as zero' => ['1.x.0', '^1.0', true];
        yield 'short version' => ['2', '^2.0', true];
        yield 'star and blank-token parts are ignored' => ['1.0.0', '>=1.0 * @dev', true];
        yield 'unknown token is ignored' => ['1.0.0', 'foo', true];
    }

    #[DataProvider('cases')]
    public function testSatisfies(string $version, string $constraint, bool $expected): void
    {
        self::assertSame($expected, ConstraintMatcher::satisfies($version, $constraint), "{$version} vs {$constraint}");
    }
}
