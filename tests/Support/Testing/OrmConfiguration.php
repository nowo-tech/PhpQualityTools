<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Testing;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;

/** An ORM configuration that works on every PHP of the CI matrix (native lazy objects on 8.4+, proxy classes before). */
final class OrmConfiguration
{
    /**
     * @param list<string> $mappingDirectories
     */
    public static function create(array $mappingDirectories = []): Configuration
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->setMetadataDriverImpl(new AttributeDriver($mappingDirectories));
        // @phpstan-ignore function.alreadyNarrowedType
        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/pqt-test-proxies');
            $config->setProxyNamespace('PqtTestProxies');
        }

        return $config;
    }
}
