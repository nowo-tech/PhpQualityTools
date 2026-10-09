<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit extension that aborts the run (exit code 1) if the test suite modified
 * this repository's own composer.json or composer.lock.
 *
 * Guards against tests that run a real `composer require` in the project root
 * (the cause of v1.0.19 shipping a composer.json out of sync with composer.lock).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class RepositoryIntegrityExtension implements Extension
{
    private const GUARDED_FILES = ['composer.json', 'composer.lock'];

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $root = \dirname(__DIR__, 2);
        $before = self::snapshot($root);

        $facade->registerSubscriber(new class($root, $before) implements ExecutionFinishedSubscriber {
            /**
             * @param array<string, string|null> $before
             */
            public function __construct(
                private readonly string $root,
                private readonly array $before,
            ) {
            }

            public function notify(ExecutionFinished $event): void
            {
                $changed = RepositoryIntegrityExtension::changedFiles($this->before, RepositoryIntegrityExtension::snapshot($this->root));
                if ([] === $changed) {
                    return;
                }

                fwrite(\STDERR, \sprintf(
                    "\n\nERROR: the test suite modified repository files: %s\n"
                    . "Tests must not run real Composer commands against the project root.\n"
                    . "Restore them with: git checkout -- %s\n",
                    implode(', ', $changed),
                    implode(' ', $changed)
                ));

                // CLI test runner only (never a FrankenPHP worker): abort so CI fails loudly.
                exit(1); // @phpstan-ignore frankenphp.classic.noExitOrDie
            }
        });
    }

    /**
     * @return array<string, string|null> File name => sha256 (null when missing)
     */
    public static function snapshot(string $root): array
    {
        $hashes = [];
        foreach (self::GUARDED_FILES as $file) {
            $path = $root . '/' . $file;
            $hashes[$file] = is_file($path) ? (hash_file('sha256', $path) ?: null) : null;
        }

        return $hashes;
    }

    /**
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     *
     * @return list<string> Names of files whose hash differs
     */
    public static function changedFiles(array $before, array $after): array
    {
        $changed = [];
        foreach ($before as $file => $hash) {
            if (($after[$file] ?? null) !== $hash) {
                $changed[] = $file;
            }
        }

        return $changed;
    }
}
