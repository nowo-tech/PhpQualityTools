<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing\Env;

use NowoTech\PhpQualityTools\Testing\Env\PidPathEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class PidPathEnvVarProcessorTest extends TestCase
{
    public function testSuffixesThePathWithTheProcessId(): void
    {
        $processor = new PidPathEnvVarProcessor();

        self::assertSame('/dev/shm/markers-' . getmypid(), $processor->getEnv('pid_path', 'X', static fn (): string => '/dev/shm/markers/'));
        self::assertSame('var/test-' . getmypid(), $processor->getEnv('pid_path', 'X', static fn (): string => 'var/test'));
        self::assertSame(['pid_path' => 'string'], PidPathEnvVarProcessor::getProvidedTypes());
    }

    public function testRejectsAnEmptyValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Env var "X" resolved by pid_path must be a non-empty string.');

        (new PidPathEnvVarProcessor())->getEnv('pid_path', 'X', static fn (): string => '');
    }

    public function testRejectsANonStringValue(): void
    {
        $this->expectException(RuntimeException::class);

        (new PidPathEnvVarProcessor())->getEnv('pid_path', 'X', static fn (): ?string => null);
    }

    public function testResolvesThroughTheContainerWithADefaultParameter(): void
    {
        $container = new ContainerBuilder();
        $container->register(PidPathEnvVarProcessor::class, PidPathEnvVarProcessor::class)
            ->addTag('container.env_var_processor');
        $container->setParameter('app.test_dir', '/dev/shm/pqt-fallback');
        $container->setParameter('marker', '%env(pid_path:default:app.test_dir:PQT_UNSET_TEST_DIR)%/setup.done');
        $container->compile(true);

        self::assertSame('/dev/shm/pqt-fallback-' . getmypid() . '/setup.done', $container->getParameter('marker'));
    }
}
