<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Minimal real kernel for the profiler-based Testing traits.
 *
 * Environments: `test` (profiler, translator, `db` collector, fake ORM manager), `nodb` (no `db` collector,
 * no manager, translator off), `badcollector` (a `db` collector without getQueryCount()), `noprofiler` (profiler off).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ProfilerTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::varDir() . '/cache/' . $this->environment . ($this->debug ? '' : '-nodebug');
    }

    public function getLogDir(): string
    {
        return self::varDir() . '/log';
    }

    public static function varDir(): string
    {
        return sys_get_temp_dir() . '/php-quality-tools-profiler-kernel-' . getmypid();
    }

    public static function rowsFile(): string
    {
        return self::varDir() . '/rows.txt';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $env = $this->environment;
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['enabled' => 'nodb' !== $env, 'default_path' => __DIR__ . '/translations', 'fallbacks' => ['en']],
            'profiler' => ['enabled' => 'noprofiler' !== $env, 'collect' => false],
        ]);

        $container->register('logger', NullLogger::class);
        $container->register(RowStore::class)->setArgument('$file', self::rowsFile())->setPublic(true);
        $container->register(QueryLog::class)->setPublic(true);
        $container->register(DemoController::class)
            ->setAutowired(true)
            ->addTag('controller.service_arguments');

        if ('test' === $env || 'noprofiler' === $env) {
            $container->register(QueryCountCollector::class)
                ->setAutowired(true)
                ->addTag('data_collector', ['id' => 'db']);
            $container->register('doctrine.orm.entity_manager', FakeObjectManager::class)
                ->setArgument('$store', new Reference(RowStore::class))
                ->setPublic(true);
        }
        if ('nodb' === $env) {
            $container->register(TranslatorInterface::class, IdentityTranslator::class);
        }
        if ('badcollector' === $env) {
            $container->register(CountlessCollector::class)->addTag('data_collector', ['id' => 'db']);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('constant', '/constant')->controller([DemoController::class, 'constant']);
        $routes->add('n_plus_one', '/n-plus-one')->controller([DemoController::class, 'nPlusOne']);
        $routes->add('fewer', '/fewer')->controller([DemoController::class, 'fewer']);
        $routes->add('missing', '/missing')->controller([DemoController::class, 'missing']);
        $routes->add('translated', '/translated')->controller([DemoController::class, 'translated']);
    }
}
