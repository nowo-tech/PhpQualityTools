<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\TwigPathsPassTestCase;
use NowoTech\PhpQualityTools\Tests\Support\Testing\BrokenTwigPathsPassProbe;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The failing cases of TwigPathsPassTestCase (the passing ones are GoodTwigPathsPassTest): a pass that breaks the
 * contract is rejected.
 */
#[CoversClass(TwigPathsPassTestCase::class)]
final class TwigPathsPassTestCaseTest extends TestCase
{
    /**
     * @return array<string, array{non-empty-string}>
     */
    public static function violatedContracts(): array
    {
        return [
            'views before the override directory' => ['testItPrependsTheOverrideDirectoryBeforeAddingTheBundleViewsWhenItExists'],
            'alias chain not followed' => ['testItFollowsAChainOfAliasesToTheLoader'],
            'native loader definition not used' => ['testItUsesTheNativeLoaderDefinition'],
        ];
    }

    /**
     * @param non-empty-string $method
     */
    #[DataProvider('violatedContracts')]
    public function testAPassThatBreaksTheContractFails(string $method): void
    {
        $probe = new BrokenTwigPathsPassProbe($method);

        try {
            $probe->{$method}();
            self::fail('The pass that breaks the contract was accepted.');
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);
        }
    }
}
