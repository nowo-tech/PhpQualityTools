<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Rector;

use NowoTech\PhpQualityTools\Rector\Rules\AddMissingReturnTypeRector;
use NowoTech\PhpQualityTools\Rector\Rules\RemoveUnusedUseStatementsRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongConstructorParametersRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongGroupedImportsRector;
use NowoTech\PhpQualityTools\Rector\Rules\SplitLongMethodCallRector;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PHPUnit\Framework\TestCase;

/**
 * Tests for custom Rector rules (definition and node types).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class RectorRulesTest extends TestCase
{
    public function testSplitLongGroupedImportsRectorDefinitionAndTypes(): void
    {
        $rector = new SplitLongGroupedImportsRector();
        $definition = $rector->getRuleDefinition();
        $this->assertInstanceOf(\Symplify\RuleDocGenerator\ValueObject\RuleDefinition::class, $definition);
        $this->assertSame([Use_::class, GroupUse::class], $rector->getNodeTypes());
    }

    public function testSplitLongConstructorParametersRectorDefinitionAndTypes(): void
    {
        $rector = new SplitLongConstructorParametersRector();
        $definition = $rector->getRuleDefinition();
        $this->assertInstanceOf(\Symplify\RuleDocGenerator\ValueObject\RuleDefinition::class, $definition);
        $this->assertSame([ClassMethod::class], $rector->getNodeTypes());
    }

    public function testAddMissingReturnTypeRectorDefinitionAndTypes(): void
    {
        $rector = new AddMissingReturnTypeRector();
        $definition = $rector->getRuleDefinition();
        $this->assertInstanceOf(\Symplify\RuleDocGenerator\ValueObject\RuleDefinition::class, $definition);
        $this->assertSame([ClassMethod::class], $rector->getNodeTypes());
    }

    public function testSplitLongMethodCallRectorDefinitionAndTypes(): void
    {
        $rector = new SplitLongMethodCallRector();
        $definition = $rector->getRuleDefinition();
        $this->assertInstanceOf(\Symplify\RuleDocGenerator\ValueObject\RuleDefinition::class, $definition);
        $this->assertSame([MethodCall::class, StaticCall::class], $rector->getNodeTypes());
    }

    public function testRemoveUnusedUseStatementsRectorDefinitionAndTypes(): void
    {
        $rector = new RemoveUnusedUseStatementsRector();
        $definition = $rector->getRuleDefinition();
        $this->assertInstanceOf(\Symplify\RuleDocGenerator\ValueObject\RuleDefinition::class, $definition);
        $this->assertSame([Use_::class], $rector->getNodeTypes());
    }

    public function testSplitLongGroupedImportsRectorRefactorReturnsNullForSingleUse(): void
    {
        $rector = new SplitLongGroupedImportsRector();
        $use = new Use_([new UseUse(new Name('Foo'))]);
        $result = $rector->refactor($use);
        $this->assertNull($result);
    }

    public function testSplitLongGroupedImportsRectorRefactorReturnsNullForShortGroupUseWithTwoItems(): void
    {
        $rector = new SplitLongGroupedImportsRector();
        $prefix = new Name('App\Ns');
        $uses = [
            new UseUse(new Name('Foo')),
            new UseUse(new Name('Bar')),
        ];
        $groupUse = new GroupUse($prefix, $uses);
        $result = $rector->refactor($groupUse);
        $this->assertNull($result);
    }

    public function testAddMissingReturnTypeRectorRefactorReturnsNullWhenHasReturnType(): void
    {
        $rector = new AddMissingReturnTypeRector();
        $method = new ClassMethod(new Identifier('test'), ['returnType' => new Identifier('string')]);
        $result = $rector->refactor($method);
        $this->assertNull($result);
    }

    public function testSplitLongGroupedImportsRectorRefactorReturnsNodeForLongGroupUse(): void
    {
        $rector = new SplitLongGroupedImportsRector();
        $prefix = new Name('App\Entity\Chat');
        $uses = [
            new UseUse(new Name('Conversation')),
            new UseUse(new Name('UserConversation')),
            new UseUse(new Name('ChatMessage')),
        ];
        $groupUse = new GroupUse($prefix, $uses);
        $result = $rector->refactor($groupUse);
        $this->assertSame($groupUse, $result);
    }

    public function testSplitLongGroupedImportsRectorRefactorWithAliasInGroupUse(): void
    {
        $rector = new SplitLongGroupedImportsRector();
        $prefix = new Name('App\Ns');
        $uses = [
            new UseUse(new Name('Foo')),
            new UseUse(new Name('Bar'), new Identifier('BarAlias')),
            new UseUse(new Name('Baz')),
        ];
        $groupUse = new GroupUse($prefix, $uses);
        $result = $rector->refactor($groupUse);
        $this->assertSame($groupUse, $result);
    }

    public function testSplitLongMethodCallRectorRefactorReturnsNullForShortChain(): void
    {
        $rector = new SplitLongMethodCallRector();
        $var = new \PhpParser\Node\Expr\Variable('this');
        $methodCall = new MethodCall($var, new Identifier('foo'));
        $result = $rector->refactor($methodCall);
        $this->assertNull($result);
    }

    public function testRemoveUnusedUseStatementsRectorRefactor(): void
    {
        $rector = new RemoveUnusedUseStatementsRector();
        $use = new Use_([new UseUse(new Name('Foo')), new UseUse(new Name('Bar'))]);
        $result = $rector->refactor($use);
        $this->assertTrue(!$result instanceof \PhpParser\Node || $result instanceof Use_);
    }
}
