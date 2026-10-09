<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support;

use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;

/**
 * Test double that records commands instead of executing them.
 *
 * Tests must never spawn a real `composer require`: it would resolve against the network
 * and rewrite this repository's own composer.json / composer.lock.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class RecordingCommandRunner implements CommandRunnerInterface
{
    /** @var list<string> Commands received, in order */
    public array $commands = [];

    /**
     * @param int          $exitCode Exit code to report for every command
     * @param list<string> $output   Output lines to report for every command
     */
    public function __construct(
        private readonly int $exitCode = 0,
        private readonly array $output = [],
    ) {
    }

    public function run(string $command): array
    {
        $this->commands[] = $command;

        return ['exitCode' => $this->exitCode, 'output' => $this->output];
    }
}
