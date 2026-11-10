<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerChecker;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerRule;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerViolation;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryChecker;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryConfig;
use PHPUnit\Framework\TestCase;

/**
 * Runs the Beacon-like layer map (tests/Fixtures/module-boundaries/beacon-layers.yaml) on a small tree.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LayerCheckerTest extends TestCase
{
    use TempTreeTrait;

    public const LAYERS_CONFIG = __DIR__ . '/../../Fixtures/module-boundaries/beacon-layers.yaml';

    /**
     * @return array<string, string>
     */
    public static function layeredTree(): array
    {
        return [
            // Uptime (enforce)
            'src/Uptime/Domain/Monitor.php' => <<<'PHP'
                <?php
                namespace App\Uptime\Domain;

                use Doctrine\Common\Collections\ArrayCollection;
                use Doctrine\ORM\Mapping as ORM;
                use App\Uptime\Infrastructure\Doctrine\MonitorType;

                #[ORM\Entity]
                final class Monitor
                {
                    // use App\Uptime\UI\Commented; $this->em->flush();
                    public function save(): string { $x = 'App\Uptime\UI\InString'; $this->em->flush(); return \App\Billing\UI\Controller\InvoiceController::class; }
                }
                PHP,
            'src/Uptime/Application/CreateMonitor.php' => <<<'PHP'
                <?php
                namespace App\Uptime\Application;

                use Doctrine\ORM\EntityManagerInterface;
                use Psr\Container\ContainerInterface;

                final class CreateMonitor { public function __invoke(EntityManagerInterface $em): void { $em->flush(); } }
                PHP,
            'src/Uptime/UI/Controller/MonitorController.php' => <<<'PHP'
                <?php
                namespace App\Uptime\UI\Controller;

                use App\Uptime\Infrastructure\Doctrine\MonitorRepository;
                use App\Billing\Repository\InvoiceRepository;
                use Doctrine\ORM\{EntityManagerInterface as Em};
                use App\Uptime\Application\CreateMonitor;

                final class MonitorController {}
                PHP,
            'src/Uptime/Infrastructure/Doctrine/MonitorRepository.php' => <<<'PHP'
                <?php
                namespace App\Uptime\Infrastructure\Doctrine;

                use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
                use Psr\Container\ContainerInterface;

                final class MonitorRepository extends ServiceEntityRepository { public function save(): void { $this->getEntityManager()->flush(); } }
                PHP,
            'src/Uptime/Legacy/Helper.php' => "<?php\nnamespace App\\Uptime\\Legacy;\n\nfinal class Helper { public function f(\$em): void { \$em->flush(); } }\n",
            'src/Uptime/README.md' => 'not php',
            // Billing (warn, today's directories mapped)
            'src/Billing/Controller/InvoiceController.php' => "<?php\nnamespace App\\Billing\\Controller;\n\nuse Doctrine\\ORM\\EntityManagerInterface;\n\nfinal class InvoiceController { public function a(EntityManagerInterface \$em): void { \$em->flush(); } }\n",
            'src/Billing/Entity/Invoice.php' => "<?php\nnamespace App\\Billing\\Entity;\n\nuse Doctrine\\ORM\\Mapping as ORM;\n\n#[ORM\\Entity]\nclass Invoice {}\n",
            'src/Billing/Repository/InvoiceRepository.php' => "<?php\nnamespace App\\Billing\\Repository;\n\nfinal class InvoiceRepository { public function s(\$em): void { \$em->flush(); } }\n",
            'src/Billing/Legacy/Old.php' => "<?php\nnamespace App\\Billing\\Legacy;\n\nuse App\\Shared\\Util\\Clock;\n",
            // Identity (warn, only UI remapped)
            'src/Identity/Controller/LoginController.php' => "<?php\nnamespace App\\Identity\\Controller;\n\nuse App\\Identity\\Repository\\UserRepository;\n",
            // Shared is not a context: never scanned by the layer rules
            'src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\n\nuse Doctrine\\ORM\\EntityManagerInterface;\n\nfinal class Clock { public function f(\$em): void { \$em->flush(); } }\n",
        ];
    }

    public function testBeaconLikeLayerMap(): void
    {
        $root = $this->makeTree(self::layeredTree());
        $config = ModuleBoundaryConfig::fromFile(self::LAYERS_CONFIG);

        $result = (new LayerChecker())->check($config, $root);

        self::assertSame(10, $result['files']);
        self::assertSame([
            'Billing warn src/Billing/Controller/InvoiceController.php:4 references Doctrine\\ORM\\EntityManagerInterface (Billing UI, layer rule ui-no-persistence): UI must not use repositories or the entity manager directly (call an Application use case).',
            'Billing warn src/Billing/Controller/InvoiceController.php:6 calls ->flush() (Billing UI, layer rule flush-in-application): flush() belongs to the Application (transaction per use case) or Infrastructure layer.',
            'Billing warn src/Billing/Entity/Invoice.php:4 references Doctrine\\ORM\\Mapping (Billing Domain, layer rule domain-framework-free): Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).',
            'Billing warn src/Billing/Entity/Invoice.php:6 references Doctrine\\ORM\\Mapping\\Entity (Billing Domain, layer rule domain-framework-free): Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).',
            'Identity warn src/Identity/Controller/LoginController.php:4 references App\\Identity\\Repository\\UserRepository (Identity UI, layer rule ui-no-persistence): UI must not use repositories or the entity manager directly (call an Application use case).',
            'Uptime enforce src/Uptime/Application/CreateMonitor.php:5 references Psr\\Container\\ContainerInterface (Uptime Application, layer rule no-container).',
            'Uptime enforce src/Uptime/Domain/Monitor.php:5 references Doctrine\\ORM\\Mapping (Uptime Domain, layer rule domain-framework-free): Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).',
            'Uptime enforce src/Uptime/Domain/Monitor.php:6 references App\\Uptime\\Infrastructure\\Doctrine\\MonitorType (Uptime Domain, layer rule domain-layer-edges): Domain must not depend on the UI or Infrastructure layer of any context.',
            'Uptime enforce src/Uptime/Domain/Monitor.php:8 references Doctrine\\ORM\\Mapping\\Entity (Uptime Domain, layer rule domain-framework-free): Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).',
            'Uptime enforce src/Uptime/Domain/Monitor.php:12 references App\\Billing\\UI\\Controller\\InvoiceController (Uptime Domain, layer rule application-no-ui): use cases do not depend on controllers, forms or Twig.',
            'Uptime enforce src/Uptime/Domain/Monitor.php:12 references App\\Billing\\UI\\Controller\\InvoiceController (Uptime Domain, layer rule domain-layer-edges): Domain must not depend on the UI or Infrastructure layer of any context.',
            'Uptime enforce src/Uptime/Domain/Monitor.php:12 calls ->flush() (Uptime Domain, layer rule flush-in-application): flush() belongs to the Application (transaction per use case) or Infrastructure layer.',
            'Uptime enforce src/Uptime/Legacy/Helper.php:4 calls ->flush() (Uptime (no layer), layer rule flush-in-application): flush() belongs to the Application (transaction per use case) or Infrastructure layer.',
            'Uptime enforce src/Uptime/UI/Controller/MonitorController.php:4 references App\\Uptime\\Infrastructure\\Doctrine\\MonitorRepository (Uptime UI, layer rule ui-no-persistence): UI must not use repositories or the entity manager directly (call an Application use case).',
            'Uptime enforce src/Uptime/UI/Controller/MonitorController.php:5 references App\\Billing\\Repository\\InvoiceRepository (Uptime UI, layer rule ui-no-other-context-infrastructure).',
            'Uptime enforce src/Uptime/UI/Controller/MonitorController.php:5 references App\\Billing\\Repository\\InvoiceRepository (Uptime UI, layer rule ui-no-persistence): UI must not use repositories or the entity manager directly (call an Application use case).',
            'Uptime enforce src/Uptime/UI/Controller/MonitorController.php:6 references Doctrine\\ORM\\EntityManagerInterface (Uptime UI, layer rule ui-no-persistence): UI must not use repositories or the entity manager directly (call an Application use case).',
        ], array_map(static fn (LayerViolation $v): string => $v->context . ' ' . $v->mode . ' ' . $v->describe(), $result['violations']));

        // The edge rules of the same map are untouched by the layers section.
        self::assertSame([], (new ModuleBoundaryChecker())->check($config, $root)['violations']);
    }

    public function testScopesAllowLayersNestedModulesAndIgnore(): void
    {
        $root = $this->makeTree([
            'lib/Shop/Core/Order.php' => "<?php\nnamespace Shop\\Core;\nuse Shop\\Web\\Page;\nuse Shop\\Infra\\Db;\nuse Blog\\Web\\Post;\nuse Blog\\Core\\Article;\nuse Blog\\Misc\\Thing;\nuse Vendor\\Lib;\n",
            'lib/Shop/Core/Generated/Proxy.php' => "<?php\nnamespace Shop\\Core\\Generated;\nuse Shop\\Web\\Page;\n",
            'lib/Shop/Plugin/Core/X.php' => "<?php\nnamespace Shop\\Plugin\\Core;\nuse Shop\\Web\\Page;\n",
            'lib/Blog/Web/Post.php' => "<?php\nnamespace Blog\\Web;\nuse Shop\\Web\\Page;\n",
        ]);
        $config = ModuleBoundaryConfig::fromArray([
            'ignore' => ['lib/*/Generated/*'],
            'modules' => [
                'Shop' => ['namespace' => 'Shop', 'path' => 'lib/Shop'],
                'Plugin' => ['namespace' => 'Shop\\Plugin', 'path' => 'lib/Shop/Plugin'],
                'Blog' => ['namespace' => 'Blog', 'path' => 'lib/Blog'],
                'Missing' => ['namespace' => 'Missing', 'path' => 'lib/Missing'],
            ],
            'layers' => [
                'definitions' => ['Core' => null, 'Web' => null, 'Infra' => null],
                'contexts' => ['Shop' => 'enforce', 'Missing' => 'warn', 'Plugin' => 'warn'],
                'rules' => [
                    ['id' => 'core-other', 'type' => 'forbid_layers', 'in' => 'Core', 'to' => ['Core', 'Web'], 'scope' => 'other'],
                    ['id' => 'core-same', 'type' => 'forbid_layers', 'in' => 'Core', 'to' => 'Web', 'scope' => 'same'],
                    ['id' => 'core-allow', 'type' => 'allow_layers', 'in' => 'Core', 'to' => 'Infra'],
                ],
            ],
        ]);

        $result = (new LayerChecker())->check($config, $root);

        self::assertSame(2, $result['files']);
        self::assertSame([
            'lib/Shop/Core/Order.php:3 references Shop\\Web\\Page (Shop Core, layer rule core-allow).',
            'lib/Shop/Core/Order.php:3 references Shop\\Web\\Page (Shop Core, layer rule core-same).',
            'lib/Shop/Core/Order.php:5 references Blog\\Web\\Post (Shop Core, layer rule core-allow).',
            'lib/Shop/Core/Order.php:5 references Blog\\Web\\Post (Shop Core, layer rule core-other).',
            'lib/Shop/Core/Order.php:6 references Blog\\Core\\Article (Shop Core, layer rule core-other).',
            'lib/Shop/Plugin/Core/X.php:3 references Shop\\Web\\Page (Plugin Core, layer rule core-allow).',
            'lib/Shop/Plugin/Core/X.php:3 references Shop\\Web\\Page (Plugin Core, layer rule core-other).',
        ], array_map(static fn (LayerViolation $v): string => $v->describe(), $result['violations']));
        self::assertSame('core-allow|lib/Shop/Core/Order.php|Shop\\Web\\Page', $result['violations'][0]->key());

        self::assertSame(['violations' => [], 'files' => 0], (new LayerChecker())->check(ModuleBoundaryConfig::fromArray([]), $root));
    }

    public function testRootModuleAndDuplicateReferences(): void
    {
        $root = $this->makeTree([
            'Domain/A.php' => "<?php\nnamespace App\\Domain;\nuse Symfony\\Foo;\nfunction f() { return \\Symfony\\Foo::class . \\Symfony\\Bar::class; }\n",
        ]);
        $config = ModuleBoundaryConfig::fromArray([
            'modules' => ['App' => ['namespace' => 'App', 'path' => '.']],
            'layers' => ['contexts' => ['App' => 'enforce']],
        ]);

        self::assertSame(
            ['Domain/A.php:3 Symfony\\Foo', 'Domain/A.php:4 Symfony\\Bar'],
            array_map(static fn (LayerViolation $v): string => $v->file . ':' . $v->line . ' ' . $v->subject, (new LayerChecker())->check($config, $root)['violations']),
        );
    }

    /**
     * KU-M1: a qualified name whose first segment is an imported namespace alias is resolved through the alias.
     */
    public function testNamespaceAliasesDoNotHideFindings(): void
    {
        $root = $this->makeTree([
            'src/Uptime/Domain/ViaModuleAlias.php' => "<?php\nnamespace App\\Uptime\\Domain;\n\nuse App\\Uptime;\n\nfunction f(Uptime\\Infrastructure\\Persistence\\MonitorRepository \$r): void {}\n",
            'src/Uptime/Domain/ViaRootAlias.php' => "<?php\nnamespace App\\Uptime\\Domain;\n\nuse App;\n\nconst C = App\\Uptime\\UI\\Controller::class;\n",
            'src/Uptime/UI/OrmAlias.php' => "<?php\nnamespace App\\Uptime\\UI;\n\nuse Doctrine\\ORM;\nuse App\\Uptime\\Infrastructure\\Persistence as P;\n\nfunction g(ORM\\EntityManagerInterface \$em, P\\MonitorRepository \$r): void {}\n",
        ]);
        $config = ModuleBoundaryConfig::fromArray([
            'modules' => ['Uptime' => ['namespace' => 'App\\Uptime', 'path' => 'src/Uptime']],
            'layers' => ['contexts' => ['Uptime' => 'enforce']],
        ]);

        self::assertSame([
            'src/Uptime/Domain/ViaModuleAlias.php:6 App\\Uptime\\Infrastructure\\Persistence\\MonitorRepository domain-layer-edges',
            'src/Uptime/Domain/ViaRootAlias.php:6 App\\Uptime\\UI\\Controller domain-layer-edges',
            'src/Uptime/UI/OrmAlias.php:7 App\\Uptime\\Infrastructure\\Persistence\\MonitorRepository ui-no-persistence',
            'src/Uptime/UI/OrmAlias.php:7 Doctrine\\ORM\\EntityManagerInterface ui-no-persistence',
        ], array_map(
            static fn (LayerViolation $v): string => $v->file . ':' . $v->line . ' ' . $v->subject . ' ' . $v->rule,
            (new LayerChecker())->check($config, $root)['violations'],
        ));
    }

    public function testNameMatches(): void
    {
        self::assertTrue(LayerRule::nameMatches('Symfony\\Component\\X', 'symfony\\'));
        self::assertTrue(LayerRule::nameMatches('Symfony', 'Symfony\\'));
        self::assertFalse(LayerRule::nameMatches('SymfonyX\\Y', 'Symfony\\'));
        self::assertTrue(LayerRule::nameMatches('Doctrine\\ORM\\EntityManagerInterface', 'doctrine\\orm\\entitymanagerinterface'));
        self::assertFalse(LayerRule::nameMatches('Doctrine\\ORM\\EntityManagerInterfaceX', 'Doctrine\\ORM\\EntityManagerInterface'));
        self::assertTrue(LayerRule::nameMatches('App\\Billing\\Repository\\InvoiceRepository', '*Repository'));
        self::assertFalse(LayerRule::nameMatches('App\\Billing\\Repository\\InvoiceRepositoryTrait', '*Repository'));
        self::assertTrue(LayerRule::nameMatches('App\\X\\Repo1', 'App\\?\\Repo?'));
        self::assertTrue(LayerRule::nameMatches('App\\X.Y', 'App\\X.Y'));
        self::assertFalse(LayerRule::nameMatches('App\\XzY', 'App\\X.*Y'));
        self::assertFalse(LayerRule::nameMatchesAny('A', []));
        self::assertTrue(LayerRule::nameMatches('App\\X\\Y', 'App**Y'));
    }
}
