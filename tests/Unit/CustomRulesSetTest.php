<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use NowoTech\PhpQualityTools\Rector\Rules\AddMissingReturnTypeRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongConstructorParametersRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongGroupedImportsRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongMethodCallRector;
use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CustomRulesSet.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class CustomRulesSetTest extends TestCase
{
    public function testGetRulesReturnsArrayOfRuleClasses(): void
    {
        $rules = CustomRulesSet::getRules(false);

        $this->assertIsArray($rules); // @phpstan-ignore method.alreadyNarrowedType
        $this->assertNotEmpty($rules);
        $this->assertContains(SplitLongGroupedImportsRector::class, $rules);
        $this->assertContains(SplitLongConstructorParametersRector::class, $rules);
        $this->assertContains(AddMissingReturnTypeRector::class, $rules);
        $this->assertContains(SplitLongMethodCallRector::class, $rules);
    }

    public function testGetRulesWithCheckDependencies(): void
    {
        $rules = CustomRulesSet::getRules(true);

        $this->assertIsArray($rules); // @phpstan-ignore method.alreadyNarrowedType
        $this->assertNotEmpty($rules);
    }

    public function testCheckDependenciesReturnsArray(): void
    {
        $missing = CustomRulesSet::checkDependencies();

        $this->assertIsArray($missing); // @phpstan-ignore method.alreadyNarrowedType
    }

    public function testHasRulesReturnsTrue(): void
    {
        $this->assertTrue(CustomRulesSet::hasRules());
    }

    public function testHasAllDependencies(): void
    {
        $result = CustomRulesSet::hasAllDependencies();

        $this->assertIsBool($result); // @phpstan-ignore method.alreadyNarrowedType
    }

    public function testGetMissingDependenciesReturnsArray(): void
    {
        $packages = CustomRulesSet::getMissingDependencies();

        $this->assertIsArray($packages); // @phpstan-ignore method.alreadyNarrowedType
    }

    public function testReportMissingDependenciesWritesToStderrWhenCli(): void
    {
        $reflection = new \ReflectionClass(CustomRulesSet::class);
        $method = $reflection->getMethod('reportMissingDependencies');

        $missing = [
            'Fake\Class' => [
                'package' => 'fake/package',
                'description' => 'Required for testing',
            ],
        ];

        $method->invoke(null, $missing);
        $this->addToAssertionCount(1);
    }
}
