<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Process;

/**
 * Executes a shell command on behalf of the Composer plugin.
 *
 * Exists so the plugin's "install suggested dependencies" flow can be unit-tested
 * with a fake runner instead of spawning a real `composer require` process.
 *
 * @internal not part of the public API; may change without notice
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
interface CommandRunnerInterface
{
    /**
     * Run a shell command and capture its combined stdout/stderr.
     *
     * @param string $command The fully escaped shell command line
     *
     * @return array{exitCode: int, output: list<string>} The exit code and output lines
     */
    public function run(string $command): array;
}
