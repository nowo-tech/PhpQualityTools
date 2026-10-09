<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use NowoTech\PhpQualityTools\Process\ExecCommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the default exec()-based command runner (harmless shell builtins only).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class ExecCommandRunnerTest extends TestCase
{
    public function testRunReturnsOutputAndZeroExitCode(): void
    {
        $result = (new ExecCommandRunner())->run('echo hello');

        $this->assertSame(['exitCode' => 0, 'output' => ['hello']], $result);
    }

    public function testRunReturnsNonZeroExitCodeAndMergesStderr(): void
    {
        $result = (new ExecCommandRunner())->run("sh -c 'echo oops 1>&2; exit 3'");

        $this->assertSame(3, $result['exitCode']);
        $this->assertSame(['oops'], $result['output']);
    }
}
