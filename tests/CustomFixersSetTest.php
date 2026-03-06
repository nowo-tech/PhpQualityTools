<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CustomFixersSet.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class CustomFixersSetTest extends TestCase
{
    public function testGetFixersReturnsArrayOfFixerInstances(): void
    {
        $fixers = CustomFixersSet::getFixers();

        $this->assertIsArray($fixers); // @phpstan-ignore method.alreadyNarrowedType
        $this->assertCount(3, $fixers);
        foreach ($fixers as $fixer) {
            $this->assertInstanceOf(\PhpCsFixer\Fixer\FixerInterface::class, $fixer);
        }
    }

    public function testGetRulesReturnsRuleConfiguration(): void
    {
        $rules = CustomFixersSet::getRules();

        $this->assertIsArray($rules); // @phpstan-ignore method.alreadyNarrowedType
        $this->assertArrayHasKey('NowoTech/multiline_grouped_imports', $rules);
        $this->assertArrayHasKey('NowoTech/multiline_array', $rules);
        $this->assertArrayHasKey('NowoTech/consistent_docblock', $rules);
        $this->assertTrue($rules['NowoTech/multiline_grouped_imports']);
        $this->assertTrue($rules['NowoTech/multiline_array']);
        $this->assertTrue($rules['NowoTech/consistent_docblock']);
    }

    public function testHasFixersReturnsTrue(): void
    {
        $this->assertTrue(CustomFixersSet::hasFixers());
    }
}
