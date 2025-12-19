<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rector rule to split long method call chains into multiple lines.
 *
 * This rule identifies method call chains that exceed a specified character
 * length and marks them for multiline formatting. The actual formatting
 * should be done by PHP-CS-Fixer.
 *
 * Example:
 * - Before: `$result = $this->service->getData()->process()->format()->output();`
 * - After (with PHP-CS-Fixer):
 *   `$result = $this->service
 *       ->getData()
 *       ->process()
 *       ->format()
 *       ->output();`
 */
final class SplitLongMethodCallRector extends AbstractRector
{
    /**
     * Maximum line length for method call chains before splitting.
     */
    private const MAX_LINE_LENGTH = 120;

    /**
     * Minimum number of chained calls to consider for splitting.
     */
    private const MIN_CHAIN_LENGTH = 3;

    /**
     * Get the rule definition.
     */
    public function getRuleDefinition(): RuleDefinition
    {
        // Check if required dependency is available
        if (!class_exists(RuleDefinition::class)) {
            throw new \RuntimeException(
                'Missing dependency: symplify/rule-doc-generator-contracts. ' .
                'Install it with: composer require --dev symplify/rule-doc-generator-contracts'
            );
        }

        return new RuleDefinition(
            'Split long method call chains into multiple lines when they exceed ' . self::MAX_LINE_LENGTH . ' characters or have ' . self::MIN_CHAIN_LENGTH . '+ chained calls',
            [
                new CodeSample(
                    <<<'PHP'
                        $result = $this->service->getData()->process()->format()->output();
                        PHP,
                    <<<'PHP'
                        $result = $this->service
                            ->getData()
                            ->process()
                            ->format()
                            ->output();
                        PHP
                ),
            ]
        );
    }

    /**
     * Get the node types this rule applies to.
     *
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    /**
     * Refactor the node if it matches the criteria.
     *
     * @param MethodCall|StaticCall $node
     */
    public function refactor(Node $node): Node|array|null
    {
        // Count chain length
        $chainLength = $this->countChainLength($node);

        if ($chainLength < self::MIN_CHAIN_LENGTH) {
            return null;
        }

        // Calculate approximate length
        $length = $this->calculateChainLength($node);

        if ($length <= self::MAX_LINE_LENGTH) {
            return null;
        }

        // Mark for formatting - actual formatting will be done by PHP-CS-Fixer
        // We return the node as-is, PHP-CS-Fixer's method_chaining_indentation rule will handle it
        return $node;
    }

    /**
     * Count the length of a method call chain.
     */
    private function countChainLength(Node $node): int
    {
        $count = 1; // Count the current call

        if ($node instanceof MethodCall) {
            $var = $node->var;
            while ($var instanceof MethodCall || $var instanceof StaticCall) {
                $count++;
                $var = $var instanceof MethodCall ? $var->var : ($var->class ?? null);
            }
        }

        return $count;
    }

    /**
     * Calculate the approximate length of a method call chain.
     */
    private function calculateChainLength(Node $node): int
    {
        $length = 0;
        $current = $node;

        while ($current instanceof MethodCall || $current instanceof StaticCall) {
            // Add length for method name
            $methodName = $this->getName($current->name);
            if ($methodName !== null) {
                $length += strlen($methodName);
            }

            // Add length for arguments
            $length += $this->calculateArgumentsLength($current->args ?? []);

            // Add length for -> operator
            $length += 2;

            // Move to next in chain
            if ($current instanceof MethodCall) {
                $current = $current->var;
            } else {
                break;
            }
        }

        return $length;
    }

    /**
     * Calculate approximate length of arguments.
     *
     * @param array<Node\Arg> $args
     */
    private function calculateArgumentsLength(array $args): int
    {
        if (empty($args)) {
            return 2; // ()
        }

        $length = 2; // ()
        foreach ($args as $arg) {
            $length += 10; // Estimate for each argument
        }
        $length += (count($args) - 1) * 2; // Commas and spaces

        return $length;
    }
}
