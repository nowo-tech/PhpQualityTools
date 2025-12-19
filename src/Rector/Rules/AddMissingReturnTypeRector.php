<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rector rule to add missing return types to public and protected methods.
 *
 * This rule adds return type declarations to public and protected methods
 * that are missing them, based on the method body analysis.
 *
 * Example:
 * - Before: `public function getName() { return $this->name; }`
 * - After: `public function getName(): string { return $this->name; }`
 */
final class AddMissingReturnTypeRector extends AbstractRector
{
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
            'Add missing return types to public and protected methods',
            [
                new CodeSample(
                    <<<'PHP'
class Example
{
    public function getName()
    {
        return $this->name;
    }
    
    protected function getCount()
    {
        return 42;
    }
}
PHP
                    ,
                    <<<'PHP'
class Example
{
    public function getName(): string
    {
        return $this->name;
    }
    
    protected function getCount(): int
    {
        return 42;
    }
}
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
        return [ClassMethod::class];
    }

    /**
     * Refactor the node if it matches the criteria.
     *
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ClassMethod) {
            return null;
        }

        // Skip if already has return type
        if ($node->returnType !== null) {
            return null;
        }

        // Only process public and protected methods
        if ($node->isPrivate()) {
            return null;
        }

        // Skip constructors and destructors
        if ($this->isName($node, '__construct') || $this->isName($node, '__destruct')) {
            return null;
        }

        // Try to infer return type from method body
        $returnType = $this->inferReturnType($node);
        if ($returnType === null) {
            return null;
        }

        // Add return type - only for simple types to avoid errors
        // Complex type inference would require more sophisticated analysis
        if (in_array(strtolower($returnType), ['string', 'int', 'float', 'bool', 'array', 'void'])) {
            $node->returnType = new Node\Name($returnType);
            return $node;
        }

        return null;
    }

    /**
     * Infer return type from method body.
     *
     * @return string|null The inferred return type or null if cannot be determined
     */
    private function inferReturnType(ClassMethod $node): ?string
    {
        // Simple inference based on return statements
        // This is a basic implementation - more sophisticated analysis could be added
        $stmts = $node->stmts ?? [];
        
        if (empty($stmts)) {
            return 'void';
        }

        $returnTypes = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_) {
                if ($stmt->expr === null) {
                    $returnTypes[] = 'void';
                } else {
                    $type = $this->getTypeFromExpression($stmt->expr);
                    if ($type !== null) {
                        $returnTypes[] = $type;
                    }
                }
            }
        }

        if (empty($returnTypes)) {
            return 'void';
        }

        // Return the most common type, or mixed if inconsistent
        $uniqueTypes = array_unique($returnTypes);
        if (count($uniqueTypes) === 1) {
            return reset($uniqueTypes);
        }

        // If multiple types, return mixed (could be improved with union types)
        return null;
    }

    /**
     * Get type from expression.
     *
     * @return string|null
     */
    private function getTypeFromExpression(Node\Expr $expr): ?string
    {
        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $this->getName($expr);
            if (in_array(strtolower($name ?? ''), ['true', 'false', 'null'])) {
                return 'bool';
            }
        }

        if ($expr instanceof Node\Scalar\String_) {
            return 'string';
        }

        if ($expr instanceof Node\Scalar\Int_) {
            return 'int';
        }

        if ($expr instanceof Node\Scalar\Float_) {
            return 'float';
        }

        if ($expr instanceof Node\Scalar\Bool_) {
            return 'bool';
        }

        if ($expr instanceof Node\Expr\Array_) {
            return 'array';
        }

        if ($expr instanceof Node\Expr\New_) {
            $class = $this->getName($expr->class);
            return $class;
        }

        if ($expr instanceof Node\Expr\MethodCall || $expr instanceof Node\Expr\StaticCall) {
            // Could try to infer from method return type, but that's complex
            return null;
        }

        return null;
    }

}

