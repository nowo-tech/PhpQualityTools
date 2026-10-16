<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Symfony env var processor `pid_path`: suffixes a filesystem path with the current process id
 * (`/dev/shm/app-markers/` → `/dev/shm/app-markers-12345`), without mutating `$_ENV` / `putenv()`.
 *
 * Parallel PHPUnit processes (ParaTest) then never share file-based state (marker files, lock or
 * scratch directories) configured from one env var: `%env(pid_path:MY_TEST_DIR)%`. Combine it with
 * `default:` for a fallback parameter: `%env(pid_path:default:app.test_dir:MY_TEST_DIR)%`.
 * `bin/clean-stale-pid-paths` removes the paths of processes that no longer exist.
 *
 * Register it in the test environment only (this package is a dev dependency), see docs/USAGE.md:
 *
 *     when@test:
 *         services:
 *             NowoTech\PhpQualityTools\Testing\Env\PidPathEnvVarProcessor:
 *                 tags: [container.env_var_processor]
 *
 * Needs `symfony/dependency-injection` (any Symfony application has it).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class PidPathEnvVarProcessor implements EnvVarProcessorInterface
{
    public const PREFIX = 'pid_path';

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $path = $getEnv($name);
        if (!\is_string($path) || '' === $path) {
            throw new RuntimeException(\sprintf('Env var "%s" resolved by %s must be a non-empty string.', $name, self::PREFIX));
        }

        return rtrim($path, '/') . '-' . getmypid();
    }

    /**
     * @return array<string, string>
     */
    public static function getProvidedTypes(): array
    {
        return [self::PREFIX => 'string'];
    }
}
