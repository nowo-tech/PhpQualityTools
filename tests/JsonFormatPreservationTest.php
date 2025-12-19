<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 *
 * @see    https://github.com/HecFranco
 */
class JsonFormatPreservationTest extends TestCase
{
    private Plugin $plugin;
    private ReflectionMethod $detectMethod;
    private ReflectionMethod $encodeMethod;

    protected function setUp(): void
    {
        $this->plugin = new Plugin();
        $reflection = new ReflectionClass($this->plugin);

        $this->detectMethod = $reflection->getMethod('detectJsonIndentation');
        $this->detectMethod->setAccessible(true);

        $this->encodeMethod = $reflection->getMethod('encodeJsonWithIndentation');
        $this->encodeMethod->setAccessible(true);
    }

    public function testDetectJsonIndentationWith2Spaces(): void
    {
        $json = "{\n  \"name\": \"test\",\n  \"scripts\": {\n    \"test\": \"phpunit\"\n  }\n}";
        $indent = $this->detectMethod->invoke($this->plugin, $json);
        $this->assertEquals('  ', $indent);
    }

    public function testDetectJsonIndentationWith4Spaces(): void
    {
        $json = "{\n    \"name\": \"test\",\n    \"scripts\": {\n        \"test\": \"phpunit\"\n    }\n}";
        $indent = $this->detectMethod->invoke($this->plugin, $json);
        $this->assertEquals('    ', $indent);
    }

    public function testDetectJsonIndentationWithTabs(): void
    {
        $json = "{\n\t\"name\": \"test\",\n\t\"scripts\": {\n\t\t\"test\": \"phpunit\"\n\t}\n}";
        $indent = $this->detectMethod->invoke($this->plugin, $json);
        $this->assertEquals("\t", $indent);
    }

    public function testEncodeJsonWith2Spaces(): void
    {
        $data = [
            'name' => 'test',
            'scripts' => [
                'test' => 'phpunit',
            ],
        ];

        $result = $this->encodeMethod->invoke($this->plugin, $data, '  ');
        $this->assertNotFalse($result);

        // Verify that the indentation is 2 spaces
        $lines = explode("\n", $result);
        $this->assertStringStartsWith('  ', $lines[1]); // First property line
    }

    public function testEncodeJsonWith4Spaces(): void
    {
        $data = [
            'name' => 'test',
            'scripts' => [
                'test' => 'phpunit',
            ],
        ];

        $result = $this->encodeMethod->invoke($this->plugin, $data, '    ');
        $this->assertNotFalse($result);

        // Verify that the indentation is 4 spaces
        $lines = explode("\n", $result);
        $this->assertStringStartsWith('    ', $lines[1]); // First property line
    }

    public function testEncodeJsonWithTabs(): void
    {
        $data = [
            'name' => 'test',
            'scripts' => [
                'test' => 'phpunit',
            ],
        ];

        $result = $this->encodeMethod->invoke($this->plugin, $data, "\t");
        $this->assertNotFalse($result);

        // Verify that the indentation is tabs
        $lines = explode("\n", $result);
        $this->assertStringStartsWith("\t", $lines[1]); // First property line
    }

    public function testPreserveOriginalIndentation(): void
    {
        // Test that when we encode with 2 spaces, decode, and re-encode with 2 spaces,
        // we get the same indentation back
        $data = [
            'scripts' => [
                'test' => 'phpunit',
                'cs-check' => 'php-cs-fixer fix --dry-run',
            ],
        ];

        // Encode with 2 spaces
        $result1 = $this->encodeMethod->invoke($this->plugin, $data, '  ');
        $this->assertNotFalse($result1);

        // Parse it back
        $parsed = json_decode($result1, true);
        $this->assertIsArray($parsed);

        // Encode again with 2 spaces
        $result2 = $this->encodeMethod->invoke($this->plugin, $parsed, '  ');
        $this->assertNotFalse($result2);

        // Both should have 2-space indentation
        $lines1 = explode("\n", $result1);
        $lines2 = explode("\n", $result2);
        $this->assertEquals('  ', substr($lines1[1], 0, 2));
        $this->assertEquals('  ', substr($lines2[1], 0, 2));
    }

    public function testDefaultIndentationIs2Spaces(): void
    {
        // Test that if we can't detect indentation, it defaults to 2 spaces
        $json = '{"name":"test"}';
        $indent = $this->detectMethod->invoke($this->plugin, $json);
        $this->assertEquals('  ', $indent);
    }
}

