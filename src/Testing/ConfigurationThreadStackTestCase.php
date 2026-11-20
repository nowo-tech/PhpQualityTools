<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;

/**
 * PHP 8.3+ stops the compiler at `zend.max_allowed_stack_size` ("Maximum call stack size ... reached during
 * compilation"). A FrankenPHP thread on Alpine (musl) has a 512 KiB stack, so a file whose compilation needs more
 * cannot be loaded there. One long fluent chain in a configuration tree needs about 1 KiB of stack per call, so the
 * trees must be built by one method per section.
 *
 * The CLI main thread of a test run has 8 MiB, so these tests run PHP in a child process with the stack capped at the
 * FrankenPHP thread size: every configuration class must load, build and process there, and every source file of the
 * package must compile.
 *
 * ```php
 * #[CoversClass(Configuration::class)]
 * final class ConfigurationThreadStackTest extends ConfigurationThreadStackTestCase
 * {
 *     protected static function configurationClasses(): array
 *     {
 *         return ['Configuration' => Configuration::class];
 *     }
 *
 *     protected static function sourceDirectory(): string
 *     {
 *         return __DIR__ . '/../../../src';
 *     }
 * }
 * ```
 *
 * Needs `phpunit/phpunit` and `symfony/config`. Before PHP 8.3 there is no such limit: the cap is ignored and the tests
 * only check that the classes load, build, process and compile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
abstract class ConfigurationThreadStackTestCase extends TestCase
{
    /** The stack of a FrankenPHP thread on Alpine (dunglas/frankenphp:*-alpine). */
    protected const THREAD_STACK = '512K';

    /**
     * @return array<string, class-string<ConfigurationInterface>> Label => configuration class
     */
    abstract protected static function configurationClasses(): array;

    /**
     * The directory whose `*.php` files must all compile on a thread stack (the package's `src/`).
     */
    abstract protected static function sourceDirectory(): string;

    /**
     * The Composer autoloader the child process loads (default: the one of the running test suite).
     */
    protected static function autoloadFile(): string
    {
        $file = (new \ReflectionClass(ClassLoader::class))->getFileName();

        return \dirname((string) $file, 2) . '/autoload.php';
    }

    /**
     * @return array<string, array{class-string<ConfigurationInterface>}>
     */
    public static function configurations(): array
    {
        $cases = [];
        foreach (static::configurationClasses() as $label => $class) {
            $cases[$label] = [$class];
        }

        return $cases;
    }

    /**
     * @param class-string<ConfigurationInterface> $class
     */
    #[DataProvider('configurations')]
    public function testTheConfigurationLoadsBuildsAndProcessesOnAFrankenPhpThreadStack(string $class): void
    {
        [$code, $stdout, $stderr] = $this->php([$this->script(), static::autoloadFile(), $class]);

        self::assertSame(0, $code, $stdout . $stderr);
        /** @var array{defaults: string, yaml: string, xml: string} $child */
        $child = json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR);
        $configuration = new $class();

        self::assertSame(ConfigurationThreadStack::inspect($class)['defaults'], $child['defaults']);
        self::assertSame((new YamlReferenceDumper())->dump($configuration), $child['yaml']);
        self::assertSame((new XmlReferenceDumper())->dump($configuration), $child['xml']);
    }

    /**
     * Every class of the package may be loaded inside a request, so every source file must compile on that stack.
     */
    public function testEverySourceFileCompilesOnAFrankenPhpThreadStack(): void
    {
        $files = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(static::sourceDirectory(), \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ('php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        self::assertNotEmpty($files);

        // php -l takes several files since PHP 8.3 and compiles each one; before, only the first.
        foreach (array_chunk($files, \PHP_VERSION_ID >= 80300 ? 50 : 1) as $chunk) {
            [$code, $stdout, $stderr] = $this->php(array_merge(['-l'], $chunk));

            self::assertSame(0, $code, $this->withoutSuccesses($stdout) . $stderr);
        }
    }

    private function script(): string
    {
        return \dirname(__DIR__, 2) . '/resources/testing/configuration-thread-stack.php';
    }

    /**
     * `php -l` prints one line per compiled file; only the failures are of interest.
     */
    private function withoutSuccesses(string $output): string
    {
        return implode("\n", array_filter(explode("\n", $output), static fn (string $line): bool => !str_starts_with($line, 'No syntax errors')));
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function php(array $args): array
    {
        // A test helper, not worker code: the pipes get a read timeout below.
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout
        $process = proc_open(
            array_merge([\PHP_BINARY, '-d', 'zend.max_allowed_stack_size=' . static::THREAD_STACK, '-d', 'xdebug.mode=off'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            \dirname(static::sourceDirectory()),
        );
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 60);
        stream_set_timeout($pipes[2], 60);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
