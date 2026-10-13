<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ImportExtractor;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ImportExtractorTest extends TestCase
{
    public function testExtractsTopLevelImportsOnly(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App\Shared;

            use App\Ops\Foo;
            use \App\Ingest\Bar as Baz;
            use App\Issues\{Repository\IssueRepository, Service\IssueMergeService as Merge};
            use function App\Ops\helper;
            use const App\Ops\LIMIT;
            use Single;
            use Group\{A, B};

            final class X
            {
                use App\Project\SomeTrait;

                public function run(): void
                {
                    $f = function () use ($x) {};
                    $g = "{$x} ${y}";
                    new \App\Performance\Entity\Span();
                }
            }
            PHP;

        $names = (new ImportExtractor())->extract($code);

        self::assertSame([
            ['name' => 'App\Ops\Foo', 'line' => 5],
            ['name' => 'App\Ingest\Bar', 'line' => 6],
            ['name' => 'App\Issues\Repository\IssueRepository', 'line' => 7],
            ['name' => 'App\Issues\Service\IssueMergeService', 'line' => 7],
            ['name' => 'App\Ops\helper', 'line' => 8],
            ['name' => 'App\Ops\LIMIT', 'line' => 9],
            ['name' => 'Single', 'line' => 10],
            ['name' => 'Group\A', 'line' => 11],
            ['name' => 'Group\B', 'line' => 11],
        ], $names);
    }

    public function testIncludesFullyQualifiedNamesOnRequest(): void
    {
        $code = "<?php\nuse App\\Ops\\Foo;\nfunction f() { return new \\App\\Performance\\Entity\\Span(); }\n";

        $names = (new ImportExtractor())->extract($code, true);

        self::assertSame([
            ['name' => 'App\Ops\Foo', 'line' => 2],
            ['name' => 'App\Performance\Entity\Span', 'line' => 3],
        ], $names);
    }

    public function testBracedNamespacesAndClosuresAtTopLevel(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App\One {
                use App\Ops\First;
                $f = function () use ($a) {};
            }
            namespace App\Two {
                use App\Ops\Second;
            }
            PHP;

        $names = (new ImportExtractor())->extract($code);

        self::assertSame([
            ['name' => 'App\Ops\First', 'line' => 3],
            ['name' => 'App\Ops\Second', 'line' => 7],
        ], $names);
    }

    public function testFileWithSyntaxErrorIsStillTokenized(): void
    {
        $names = (new ImportExtractor())->extract("<?php\nuse App\\Ops\\Foo;\nclass {\n");

        self::assertSame([['name' => 'App\Ops\Foo', 'line' => 2]], $names);
    }

    public function testUnterminatedUseStatement(): void
    {
        self::assertSame([['name' => 'App\Ops\Foo', 'line' => 2]], (new ImportExtractor())->extract("<?php\nuse App\\Ops\\Foo"));
    }
}
