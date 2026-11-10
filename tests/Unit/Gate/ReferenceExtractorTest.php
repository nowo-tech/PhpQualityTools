<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ReferenceExtractor;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ReferenceExtractorTest extends TestCase
{
    public function testImportsAliasesQualifiedNamesAndCalls(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App\Uptime\Domain;

            use Doctrine\ORM\Mapping as ORM;
            use App\Uptime\{Infrastructure\Repo as R, UI\Ctl};
            use function Symfony\Component\String\u;
            use const App\Limits\MAX;
            use App\Shared\{function helper, const LIMIT, Thing};
            // use App\Commented\Out;
            /** @see \App\InDocBlock */
            #[ORM\Entity]
            final class Monitor
            {
                use \App\Traits\T;
                use Local\Traits\U;

                public function x(): string
                {
                    $s = 'App\Strings\No' . "\App\Strings\Neither {$s}" . <<<TXT
                        \App\Heredoc\Nope
                        TXT;
                    $c = function () use ($s) {};
                    $this->em?->flush();
                    $o->FLUSH ();
                    $o->flush;
                    Thing::persist();
                    u('x');

                    return Infrastructure\Thing::class . \App\Uptime\UI\X::class . namespace\Rel::class . R::class . Ctl\Sub::class;
                }
            }
            PHP;

        $result = (new ReferenceExtractor())->extract($code);

        self::assertSame([
            ['name' => 'Doctrine\\ORM\\Mapping', 'line' => 4],
            ['name' => 'App\\Uptime\\Infrastructure\\Repo', 'line' => 5],
            ['name' => 'App\\Uptime\\UI\\Ctl', 'line' => 5],
            ['name' => 'Symfony\\Component\\String\\u', 'line' => 6],
            ['name' => 'App\\Limits\\MAX', 'line' => 7],
            ['name' => 'App\\Shared\\helper', 'line' => 8],
            ['name' => 'App\\Shared\\LIMIT', 'line' => 8],
            ['name' => 'App\\Shared\\Thing', 'line' => 8],
            ['name' => 'App\\Traits\\T', 'line' => 14],
            ['name' => 'App\\Uptime\\Domain\\Local\\Traits\\U', 'line' => 15],
            ['name' => 'App\\Uptime\\Domain\\Infrastructure\\Thing', 'line' => 29],
            ['name' => 'App\\Uptime\\UI\\X', 'line' => 29],
            ['name' => 'App\\Uptime\\Domain\\Rel', 'line' => 29],
        ], $result['names']);
        self::assertSame([['method' => 'flush', 'line' => 23], ['method' => 'flush', 'line' => 24]], $result['calls']);
    }

    public function testFunctionImportsAreNotAliasesAndNamespacesReset(): void
    {
        $code = <<<'PHP'
            <?php
            namespace A {
                use function Lib\Infra;
                use Lib\Models;
                $x = Infra\Thing::class . Models\User::class;
                $f = function () use ($x) {};
            }
            namespace B {
                $y = Models\User::class;
            }
            namespace {
                $z = Root\Thing::class;
            }
            PHP;

        self::assertSame([
            ['name' => 'Lib\\Infra', 'line' => 3],
            ['name' => 'Lib\\Models', 'line' => 4],
            ['name' => 'A\\Infra\\Thing', 'line' => 5],
            ['name' => 'B\\Models\\User', 'line' => 9],
            ['name' => 'Root\\Thing', 'line' => 12],
        ], (new ReferenceExtractor())->extract($code)['names']);
    }

    public function testParseErrorFallsBackToPlainTokens(): void
    {
        $result = (new ReferenceExtractor())->extract("<?php\nuse A\\B;\nuse C\\D E;\nclass {\n\$x->flush(\n");

        self::assertSame([['name' => 'A\\B', 'line' => 2], ['name' => 'C\\D', 'line' => 3]], $result['names']);
        self::assertSame([['method' => 'flush', 'line' => 5]], $result['calls']);
    }
}
