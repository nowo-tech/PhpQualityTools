<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\PhpSkeleton;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class PhpSkeletonTest extends TestCase
{
    private const BASE = <<<'PHP'
        <?php

        namespace App\Billing;

        use App\Shared\Money;

        #[Service]
        final class Invoice extends Document implements Payable, \Countable
        {
            use Timestamps;
            use \App\Shared\SoftDeletes { restore as private; }

            public const RATE = 21;

            private int $lines = 0;

            /** @return int */
            public function total(int $amount): int
            {
                $label = "total {$amount}";

                return $amount * self::RATE;
            }

            abstract protected function key(): string;

            public function count(): int
            {
                return $this->lines;
            }
        }
        PHP;

    public function testRecordsBodiesDeclarationsAndParents(): void
    {
        $skeleton = PhpSkeleton::analyse(self::BASE);

        self::assertSame([[19, 23], [28, 30]], array_map(static fn (array $body): array => [$body[0], $body[1]], $skeleton->bodies));
        self::assertSame(['Invoice'], $skeleton->declares);
        self::assertSame(['Document', 'Payable', 'Countable', 'Timestamps', 'SoftDeletes'], $skeleton->parents);
    }

    public function testABodyOnlyChangeKeepsTheSkeletonAndChangesThatBodyHash(): void
    {
        $before = PhpSkeleton::analyse(self::BASE);
        $after = PhpSkeleton::analyse(str_replace(
            "        return \$amount * self::RATE;\n",
            "        // rounded\n        \$amount = max(0, \$amount);\n\n        return \$amount * self::RATE;\n",
            self::BASE,
        ));

        self::assertSame($before->skeleton, $after->skeleton);
        self::assertNotSame($before->bodies[0][2], $after->bodies[0][2]);
        self::assertSame($before->bodies[1][2], $after->bodies[1][2]);
        self::assertSame([28 + 3, 30 + 3], [$after->bodies[1][0], $after->bodies[1][1]], 'line ranges follow the new content');
    }

    public function testWhitespaceAndLineCommentsOutsideBodiesDoNotCount(): void
    {
        $changed = str_replace("    public const RATE = 21;\n", "    // the VAT rate\n    public const RATE   =   21;\n\n", self::BASE);

        self::assertSame(PhpSkeleton::analyse(self::BASE)->skeleton, PhpSkeleton::analyse($changed)->skeleton);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function skeletonChanges(): iterable
    {
        yield 'signature' => ['public function total(int $amount): int', 'public function total(int $amount, int $rate = 1): int'];
        yield 'return type' => ['public function count(): int', 'public function count(): ?int'];
        yield 'constant' => ['public const RATE = 21;', 'public const RATE = 10;'];
        yield 'property default' => ['private int $lines = 0;', 'private int $lines = 1;'];
        yield 'attribute' => ['#[Service]', '#[Service(public: true)]'];
        yield 'doc comment' => ['/** @return int */', '/** @return positive-int */'];
        yield 'import' => ['use App\Shared\Money;', 'use App\Other\Money;'];
        yield 'parent' => ['extends Document', 'extends Draft'];
        yield 'interface' => ['implements Payable, \Countable', 'implements Payable'];
        yield 'trait' => ['use Timestamps;', 'use Timestamps, Versioned;'];
        yield 'abstract made concrete' => ['abstract protected function key(): string;', 'protected function key(): string { return "k"; }'];
        yield 'new method' => ['    public function count(): int', "    public function sum(): int\n    {\n        return 1;\n    }\n\n    public function count(): int"];
        yield 'generator' => ['        return $this->lines;', '        yield $this->lines;'];
    }

    #[DataProvider('skeletonChanges')]
    public function testAChangeOutsideBodiesChangesTheSkeleton(string $search, string $replace): void
    {
        $changed = str_replace($search, $replace, self::BASE);
        self::assertNotSame(self::BASE, $changed, 'the fixture replacement applies');

        self::assertNotSame(PhpSkeleton::analyse(self::BASE)->skeleton, PhpSkeleton::analyse($changed)->skeleton);
    }

    public function testNestedClosuresAnonymousClassesAndInterpolationStayInTheOuterBody(): void
    {
        $code = <<<'PHP'
            <?php
            use function strlen;
            function outer(\Closure $fallback = static function (): int { return 1; }): object
            {
                $fn = function () use ($fallback) { return "{$fallback()} ${fallback}"; };
                $arrow = fn () => strlen('x');
                return new class () extends Base {
                    public function inner(): void {}
                };
            }
            interface Shape { public function area(): float; }
            enum Suit: string { case Hearts = 'H'; public function label(): string { return Suit::class; } }
            trait Greets {}
            $instance = new class {};
            PHP;

        $skeleton = PhpSkeleton::analyse($code);

        self::assertSame([[3, 3], [4, 10], [12, 12]], array_map(static fn (array $body): array => [$body[0], $body[1]], $skeleton->bodies), 'the default-value closure, outer() and label()');
        self::assertSame(['Shape', 'Suit', 'Greets'], $skeleton->declares);
        self::assertSame([], $skeleton->parents, 'the anonymous class inside a body is not part of the skeleton');
    }

    public function testAnUnterminatedBodyRunsToTheEndOfTheFile(): void
    {
        $skeleton = PhpSkeleton::analyse("<?php\nfunction broken() {\n    if (true) {\n");

        self::assertSame([[2, 3]], array_map(static fn (array $body): array => [$body[0], $body[1]], $skeleton->bodies));
    }

    public function testAClassKeywordAtTheEndDeclaresNothing(): void
    {
        self::assertSame([], PhpSkeleton::analyse('<?php echo X::class;')->declares);
        self::assertSame([], PhpSkeleton::analyse('<?php class')->declares);
    }
}
