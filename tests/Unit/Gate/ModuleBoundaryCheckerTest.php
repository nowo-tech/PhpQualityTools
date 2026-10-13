<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryChecker;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryConfig;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the Beacon module-boundary map (tests/Fixtures/module-boundaries/beacon.yaml) on a small fake
 * module tree: the clean tree passes, and each former shell-script check fails on its regression.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryCheckerTest extends TestCase
{
    use TempTreeTrait;

    public const BEACON_CONFIG = __DIR__ . '/../../Fixtures/module-boundaries/beacon.yaml';

    /**
     * Minimal tree satisfying every Beacon rule.
     *
     * @return array<string, string|null>
     */
    public static function beaconTree(): array
    {
        $entity = "<?php\nnamespace App\\%s\\Entity;\nuse Doctrine\\ORM\\Mapping as ORM;\nclass %s\n{\n    #[ORM\\OneToMany(\n        mappedBy: 'x',\n        targetEntity: Y::class,\n        fetch: 'EXTRA_LAZY',\n    )]\n    private \$items;\n}\n";

        return [
            'src/Project/Controller/AdminProjectController.php' => "<?php\nnamespace App\\Project\\Controller;\nfinal class AdminProjectController {}\n",
            'src/Project/Port/ProjectMembershipAdminPort.php' => "<?php\nnamespace App\\Project\\Port;\ninterface ProjectMembershipAdminPort {}\n",
            'src/Project/Entity/Project.php' => \sprintf($entity, 'Project', 'Project'),
            'src/Project/Service/ProjectGovernanceResolver.php' => "<?php\nnamespace App\\Project\\Service;\nuse App\\Ingest\\Service\\EventQuotaUsageStore;\nfinal class ProjectGovernanceResolver {}\n",
            'src/Project/Service/ProjectSettingsPageBuilder.php' => "<?php\nnamespace App\\Project\\Service;\nuse App\\Ops\\Messenger\\MessengerQueueHealth;\nfinal class ProjectSettingsPageBuilder {}\n",
            'src/Issues/Entity/Issue.php' => \sprintf($entity, 'Issues', 'Issue'),
            'src/Performance/Entity/PerfTransaction.php' => \sprintf($entity, 'Performance', 'PerfTransaction'),
            'src/Identity/Controller/AdminUserController.php' => "<?php\nnamespace App\\Identity\\Controller;\nuse App\\Project\\Port\\ProjectMembershipAdminPort;\nfinal class AdminUserController {}\n",
            'src/Ingest/Otlp/Controller/OtlpTracesController.php' => "<?php\nnamespace App\\Ingest\\Otlp\\Controller;\nfinal class OtlpTracesController {}\n",
            'src/Ingest/MessageHandler/ProcessEnvelopeHandler.php' => "<?php\nnamespace App\\Ingest\\MessageHandler;\nuse App\\Issues\\Ingest\\IssueEnvelopeWriter;\nuse App\\Performance\\Ingest\\PerformanceEnvelopeWriter;\nfinal class ProcessEnvelopeHandler {}\n",
            'src/Ops/Service/OpsOverviewService.php' => "<?php\nnamespace App\\Ops\\Service;\nuse App\\Issues\\Repository\\IssueRepository;\nfinal class OpsOverviewService {}\n",
            'src/Ops/Retention/RetentionPurger.php' => "<?php\nnamespace App\\Ops\\Retention;\nfinal class RetentionPurger {}\n",
            'src/Ops/Metrics/MetricsController.php' => "<?php\nnamespace App\\Ops\\Metrics;\nfinal class MetricsController {}\n",
            'src/Shared/Doctrine/SqlLikeEscaper.php' => "<?php\nnamespace App\\Shared\\Doctrine;\nfinal class SqlLikeEscaper {}\n",
            'src/Shared/Beacon/DropSelfIngestBeforeSend.php' => "<?php\nnamespace App\\Shared\\Beacon;\nuse App\\Ingest\\Message\\ProcessEnvelopeMessage;\nfinal class DropSelfIngestBeforeSend {}\n",
            'src/Shared/Listing/ListingLayoutResolver.php' => "<?php\nnamespace App\\Shared\\Listing;\nuse App\\Project\\Entity\\Project;\nfinal class ListingLayoutResolver {}\n",
            'src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse Psr\\Clock\\ClockInterface;\nfinal class Clock {}\n",
            'src/Kernel.php' => "<?php\nnamespace App;\nuse App\\Ops\\Anything;\nfinal class Kernel {}\n",
        ];
    }

    public function testCleanBeaconTreePasses(): void
    {
        $root = $this->makeTree(self::beaconTree());

        $result = (new ModuleBoundaryChecker())->check(ModuleBoundaryConfig::fromFile(self::BEACON_CONFIG), $root);

        self::assertSame([], $this->messages($result['violations']));
        self::assertSame(17, $result['files']);
    }

    /**
     * @param array<string, string|null> $changes file => content (null deletes)
     */
    #[DataProvider('regressionProvider')]
    public function testBeaconRegressionIsReported(array $changes, string $expected): void
    {
        $root = $this->makeTree([...self::beaconTree(), ...$changes]);

        $result = (new ModuleBoundaryChecker())->check(ModuleBoundaryConfig::fromFile(self::BEACON_CONFIG), $root);
        $messages = $this->messages($result['violations']);

        self::assertCount(1, $result['violations'], implode("\n", $messages));
        self::assertStringContainsString($expected, $messages[0]);
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string}>
     */
    public static function regressionProvider(): iterable
    {
        yield 'AdminProjectController file under Identity' => [
            ['src/Identity/Controller/AdminProjectController.php' => "<?php\nnamespace App\\Identity\\Controller;\n"],
            "src/Identity/Controller/AdminProjectController.php must not exist (belongs in App\\Project\\Controller).\nsrc/Identity/Controller/AdminProjectController.php",
        ];
        yield 'AdminProjectController class under Identity' => [
            ['src/Identity/Admin/Legacy.php' => "<?php\n\nfinal class AdminProjectController {}\n"],
            "AdminProjectController must not live under App\\Identity.\nsrc/Identity/Admin/Legacy.php:3",
        ];
        yield 'AdminProjectController missing from Project' => [
            ['src/Project/Controller/AdminProjectController.php' => null],
            "Required path(s) missing:\nsrc/Project/Controller/AdminProjectController.php",
        ];
        yield 'membership port missing' => [
            ['src/Project/Port/ProjectMembershipAdminPort.php' => null],
            'src/Project/Port/ProjectMembershipAdminPort.php',
        ];
        yield 'Identity controller uses ProjectMembershipManager' => [
            ['src/Identity/Controller/AdminUserController.php' => "<?php\nnamespace App\\Identity\\Controller;\nuse App\\Project\\Service\\ProjectMembershipManager;\n"],
            "not ProjectMembershipManager:\nsrc/Identity/Controller/AdminUserController.php:3 imports App\\Project\\Service\\ProjectMembershipManager",
        ];
        yield 'OTLP controller outside Ingest/Otlp' => [
            ['src/Api/OtlpLogsController.php' => "<?php\nnamespace App\\Api;\nfinal class OtlpLogsController {}\n"],
            "OTLP controllers must live under src/Ingest/Otlp/:\nsrc/Api/OtlpLogsController.php:3",
        ];
        yield 'Ingest/Otlp missing' => [
            ['src/Ingest/Otlp/Controller/OtlpTracesController.php' => null],
            'missing src/Ingest/Otlp (OTLP adapters belong here).',
        ];
        yield 'Shared imports a write-path repository' => [
            ['src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse App\\Issues\\Repository\\EventRepository;\n"],
            "Shared must not import Issues/Performance write-path types (use App\\Ops for retention/ops):\nsrc/Shared/Util/Clock.php:3 imports App\\Issues\\Repository\\EventRepository",
        ];
        yield 'Shared imports a Performance entity' => [
            ['src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse App\\Performance\\Entity\\Span;\n"],
            'imports App\\Performance\\Entity\\Span',
        ];
        yield 'Ops overview missing' => [
            ['src/Ops/Service/OpsOverviewService.php' => null],
            'src/Ops/Service/OpsOverviewService.php',
        ];
        yield 'envelope handler re-inlines issue persist' => [
            ['src/Ingest/MessageHandler/ProcessEnvelopeHandler.php' => "<?php\nuse App\\Performance\\Ingest\\PerformanceEnvelopeWriter;\n"],
            "ProcessEnvelopeHandler must use IssueEnvelopeWriter (domain writer extraction).\nsrc/Ingest/MessageHandler/ProcessEnvelopeHandler.php",
        ];
        yield 'Shared/Metrics directory' => [
            ['src/Shared/Metrics/README.md' => 'moved'],
            "Metrics scrape belongs in App\\Ops\\Metrics (remove src/Shared/Metrics).\nsrc/Shared/Metrics",
        ];
        yield 'Shared\\Metrics namespace elsewhere' => [
            ['src/Twig/MetricsExtension.php' => "<?php\nnamespace App\\Shared\\Metrics;\n"],
            "namespace App\\Shared\\Metrics is forbidden; use App\\Ops\\Metrics:\nsrc/Twig/MetricsExtension.php:2",
        ];
        yield 'Identity uses ProjectCreationFormFactory' => [
            ['src/Identity/Dashboard/Home.php' => "<?php\nnamespace App\\Identity\\Dashboard;\nuse App\\Project\\Service\\ProjectCreationFormFactory;\n"],
            'Identity must not depend on ProjectCreationFormFactory',
        ];
        yield 'SqlLikeEscaper missing' => [
            ['src/Shared/Doctrine/SqlLikeEscaper.php' => null],
            'missing src/Shared/Doctrine/SqlLikeEscaper.php (LIKE queries must escape wildcards).',
        ];
        yield 'OneToMany without EXTRA_LAZY' => [
            ['src/Issues/Entity/Issue.php' => "<?php\nclass Issue\n{\n    #[ORM\\OneToMany(mappedBy: 'issue', targetEntity: Event::class)]\n    private \$events;\n}\n"],
            "OneToMany collection(s) without fetch: 'EXTRA_LAZY' (REQ-BP-004):\nsrc/Issues/Entity/Issue.php:4",
        ];
        yield 'Shared imports Ops' => [
            ['src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse App\\Ops\\Messenger\\MessengerQueueHealth;\n"],
            'src/Shared/Util/Clock.php:3 imports App\\Ops\\Messenger\\MessengerQueueHealth (Shared -> Ops is forbidden): Shared/Project must not depend on App\\Ops or App\\Ingest (see spec 131).',
        ];
        yield 'Project imports Ingest outside the allowlist' => [
            ['src/Project/Service/NewService.php' => "<?php\nnamespace App\\Project\\Service;\nuse App\\Ingest\\Service\\EventQuotaUsageStore;\n"],
            'src/Project/Service/NewService.php:3 imports App\\Ingest\\Service\\EventQuotaUsageStore (Project -> Ingest is forbidden)',
        ];
        yield 'Shared imports Project outside Listing' => [
            ['src/Shared/Util/Clock.php' => "<?php\nnamespace App\\Shared\\Util;\nuse App\\Project\\Entity\\Project;\n"],
            'src/Shared/Util/Clock.php:3 imports App\\Project\\Entity\\Project (Shared -> Project is forbidden): only src/Shared/Listing/ may import App\\Project',
        ];
    }

    public function testAllowedEdgesIgnoreAndFullyQualifiedReferences(): void
    {
        $root = $this->makeTree([
            'lib/Core/Model.php' => "<?php\nnamespace Acme\\Core;\nuse Acme\\Web\\Request;\nuse Vendor\\Thing;\n",
            'lib/Core/Fqcn.php' => "<?php\nnamespace Acme\\Core;\nfunction f() { return new \\Acme\\Web\\Response(); }\n",
            'lib/Core/Generated/Proxy.php' => "<?php\nnamespace Acme\\Core\\Generated;\nuse Acme\\Web\\Request;\n",
            'lib/Core/Sub/Inner.php' => "<?php\nnamespace Acme\\Core\\Sub;\nuse Acme\\Core\\Model;\nuse Acme\\Web\\Request;\n",
            'lib/Web/Controller.php' => "<?php\nnamespace Acme\\Web;\nuse Acme\\Core\\Model;\n",
            'lib/Web/notes.txt' => 'not php',
            'bin/console.php' => "<?php\nuse Acme\\Web\\Request;\n",
        ]);
        $config = ModuleBoundaryConfig::fromArray([
            'references' => 'all',
            'paths' => ['lib', 'bin'],
            'ignore' => ['lib/*/Generated/*'],
            'modules' => [
                'Core' => ['namespace' => 'Acme\\Core', 'path' => 'lib/Core/'],
                'CoreSub' => ['namespace' => 'Acme\\Core\\Sub', 'path' => 'lib/Core/Sub'],
                'Web' => ['namespace' => '\\Acme\\Web\\', 'path' => './lib/Web'],
                'External' => 'Vendor',
            ],
            'allowed_edges' => ['Core' => ['External'], 'CoreSub' => ['Core']],
        ]);

        $result = (new ModuleBoundaryChecker())->check($config, $root);

        self::assertSame([
            'lib/Core/Fqcn.php:3 imports Acme\\Web\\Response (Core -> Web is not an allowed edge).',
            'lib/Core/Model.php:3 imports Acme\\Web\\Request (Core -> Web is not an allowed edge).',
            'lib/Core/Sub/Inner.php:4 imports Acme\\Web\\Request (CoreSub -> Web is not an allowed edge).',
        ], $this->messages($result['violations']));
        self::assertSame(5, $result['files']);
    }

    public function testRulesWithoutMessagesUseDefaults(): void
    {
        $root = $this->makeTree([
            'src/A.php' => "<?php\nuse Bad\\Thing;\n// TODO\n#[Many]\n\n",
            'src/legacy/B.php' => "<?php\n// TODO\n",
            'present.txt' => 'x',
        ]);
        $config = ModuleBoundaryConfig::fromArray([
            'paths' => ['src'],
            'ignore' => ['src/legacy/'],
            'rules' => [
                ['type' => 'forbid_path', 'path' => 'present.txt'],
                ['type' => 'require_path', 'path' => ['missing.txt', 'present.txt']],
                ['type' => 'forbid_import', 'imports' => 'Bad\\'],
                ['type' => 'forbid_pattern', 'pattern' => 'TODO'],
                ['type' => 'require_pattern', 'path' => ['src/A.php', 'src/missing.php'], 'pattern' => 'declare'],
                ['type' => 'require_near', 'path' => 'src', 'pattern' => '#\[Many\]', 'requires' => 'Lazy', 'within_lines' => 1],
            ],
        ]);

        $violations = (new ModuleBoundaryChecker())->check($config, $root . '/')['violations'];

        self::assertSame([
            "Forbidden path(s) exist:\npresent.txt",
            "Required path(s) missing:\nmissing.txt",
            "Forbidden import(s):\nsrc/A.php:2 imports Bad\\Thing",
            "Forbidden pattern ~TODO~m found in:\nsrc/A.php:3",
            "Required pattern ~declare~m not found in:\nsrc/A.php",
            "~#\\[Many\\]~m without ~Lazy~m within 1 line(s):\nsrc/A.php:4",
        ], $this->messages($violations));
    }

    public function testNoEdgesConfiguredSkipsImportScan(): void
    {
        $root = $this->makeTree(['src/A.php' => "<?php\nuse Anything;\n"]);
        $config = ModuleBoundaryConfig::fromArray(['modules' => ['A' => ['namespace' => 'A', 'path' => 'src']]]);

        $result = (new ModuleBoundaryChecker())->check($config, $root);

        self::assertSame([], $result['violations']);
        self::assertSame(1, $result['files']);
    }

    public function testRootAsScanPath(): void
    {
        $root = $this->makeTree(['A.php' => "<?php\n// TODO\n"]);
        $config = ModuleBoundaryConfig::fromArray([
            'paths' => ['.'],
            'rules' => [['type' => 'forbid_pattern', 'pattern' => 'TODO', 'message' => 'no TODO']],
        ]);

        self::assertSame(["no TODO\nA.php:2"], $this->messages((new ModuleBoundaryChecker())->check($config, $root)['violations']));
    }

    public function testPathMatches(): void
    {
        self::assertTrue(ModuleBoundaryChecker::pathMatches('src/A/B.php', 'src/A'));
        self::assertTrue(ModuleBoundaryChecker::pathMatches('src/A/B.php', 'src/A/'));
        self::assertTrue(ModuleBoundaryChecker::pathMatches('src/A', 'src/A/'));
        self::assertFalse(ModuleBoundaryChecker::pathMatches('src/AB/C.php', 'src/A'));
        self::assertTrue(ModuleBoundaryChecker::pathMatches('src/x/Legacy/y/C.php', 'src/*/Legacy/*'));
        self::assertFalse(ModuleBoundaryChecker::pathMatches('src/C.php', 'src/*/Legacy/*'));
        self::assertTrue(ModuleBoundaryChecker::pathMatches('src/C.php', ''));
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<string>
     */
    private function messages(array $violations): array
    {
        return array_map(
            static fn (Violation $v): string => implode("\n", [$v->message, ...$v->details]),
            $violations,
        );
    }
}
