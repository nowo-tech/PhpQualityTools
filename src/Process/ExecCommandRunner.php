<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Process;

/**
 * Default command runner backed by PHP's exec(); stderr is merged into the output.
 * The command runs in the current working directory (the project root during Composer events).
 *
 * @internal not part of the public API; may change without notice
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ExecCommandRunner implements CommandRunnerInterface
{
    public function run(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);

        return ['exitCode' => $exitCode, 'output' => $output];
    }
}
