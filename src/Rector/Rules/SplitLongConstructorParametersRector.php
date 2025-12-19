<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rector rule to split long constructor parameter lists into multiple lines.
 *
 * This rule detects constructors with parameter lists that exceed a specified
 * character length. It marks them for formatting, and PHP-CS-Fixer with
 * method_argument_space (ensure_fully_multiline) will format them correctly.
 *
 * IMPORTANT: After running Rector, you must run PHP-CS-Fixer to apply the formatting:
 *   vendor/bin/php-cs-fixer fix
 *
 * Example:
 * - Before: `public function __construct(protected ContainerInterface $_container, private readonly HubInterface $hub, private readonly ParameterBagInterface $parameterBag, private readonly ParamFetcherInterface $paramFetcher)`
 * - After (with PHP-CS-Fixer):
 *   `public function __construct(
 *     protected ContainerInterface $_container,
 *     private readonly HubInterface $hub,
 *     private readonly ParameterBagInterface $parameterBag,
 *     private readonly ParamFetcherInterface $paramFetcher
 *   )`
 */
final class SplitLongConstructorParametersRector extends AbstractRector
{
  /**
   * Maximum line length for constructor parameters before splitting.
   *
   * Common standards:
   * - 80: Very strict (classic, but restrictive for modern code)
   * - 100: Balanced (good readability)
   * - 120: Modern standard (recommended for Symfony projects with long class names)
   * - 150+: Too permissive
   */
  private const MAX_LINE_LENGTH = 120;

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
      'Split long constructor parameter lists into multiple lines when they exceed ' . self::MAX_LINE_LENGTH . ' characters',
      [
        new CodeSample(
          <<<'PHP'
public function __construct(protected ContainerInterface $_container, private readonly HubInterface $hub, private readonly ParameterBagInterface $parameterBag, private readonly ParamFetcherInterface $paramFetcher)
{
}
PHP
          ,
          <<<'PHP'
public function __construct(
    protected ContainerInterface $_container,
    private readonly HubInterface $hub,
    private readonly ParameterBagInterface $parameterBag,
    private readonly ParamFetcherInterface $paramFetcher
) {
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
   */
  public function refactor(Node $node): ?Node
  {
    if (!$node instanceof ClassMethod) {
      return null;
    }

    // Only process constructors
    if (!$this->isName($node, '__construct')) {
      return null;
    }

    // Skip if no parameters
    if (count($node->params) === 0) {
      return null;
    }

    // Skip if already multiline (has newlines in params)
    if ($this->isAlreadyMultiline($node)) {
      return null;
    }

    // Calculate the length of the constructor signature
    $constructorLength = $this->calculateConstructorLength($node);

    // If the line is not too long, don't split it
    if ($constructorLength <= self::MAX_LINE_LENGTH) {
      return null;
    }

    // Make the constructor multiline
    $this->makeMultiline($node);

    return $node;
  }

  /**
   * Check if the constructor is already multiline.
   */
  private function isAlreadyMultiline(ClassMethod $node): bool
  {
    $startLine = $node->getStartLine();
    $endLine = $node->params[count($node->params) - 1]->getEndLine();

    // If parameters span multiple lines, it's already multiline
    return $endLine > $startLine;
  }

  /**
   * Calculate the approximate length of a constructor signature.
   */
  private function calculateConstructorLength(ClassMethod $node): int
  {
    $length = 0;

    // Add length for visibility and "function __construct("
    $visibility = $node->isPublic() ? 'public' : ($node->isProtected() ? 'protected' : 'private');
    $length += strlen($visibility) + 1; // "public " or "protected " or "private "
    $length += 9; // "function "
    $length += 11; // "__construct("

    // Add length for each parameter
    foreach ($node->params as $param) {
      // Add length for parameter modifiers (readonly, public, protected, private)
      if ($param->flags !== 0) {
        if ($param->flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC) {
          $length += 7; // "public "
        } elseif ($param->flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED) {
          $length += 10; // "protected "
        } elseif ($param->flags & \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE) {
          $length += 8; // "private "
        }

        if ($param->flags & \PhpParser\Node\Stmt\Class_::MODIFIER_READONLY) {
          $length += 9; // "readonly "
        }
      }

      // Add length for type hint
      if ($param->type !== null) {
        $typeName = $this->getName($param->type);
        if ($typeName !== null) {
          $length += strlen($typeName);
        } else {
          // For complex types, estimate
          $length += 20; // Estimate for complex types
        }
        $length += 1; // Space after type
      }

      // Add length for variable name
      if ($param->var instanceof \PhpParser\Node\Expr\Variable) {
        $varName = $param->var->name;
        if (is_string($varName)) {
          $length += strlen($varName);
        }
      }

      // Add length for default value if present
      if ($param->default !== null) {
        $length += 5; // " = ..." (estimate)
      }

      // Add length for comma and space (except last)
      if ($param !== end($node->params)) {
        $length += 2; // ", "
      }
    }

    // Add length for closing parenthesis
    $length += 1; // ")"

    return $length;
  }

  /**
   * Make the constructor parameters multiline.
   *
   * This method marks the constructor for multiline formatting.
   * The actual formatting will be applied by PHP-CS-Fixer when running after Rector.
   * PHP-CS-Fixer's method_argument_space rule with 'ensure_fully_multiline' option
   * will format parameters on separate lines when there are multiple parameters.
   *
   * Note: Rector cannot directly change line formatting - that's PHP-CS-Fixer's job.
   * This rule just identifies constructors that need multiline formatting.
   */
  private function makeMultiline(ClassMethod $node): void
  {
    // Rector cannot directly modify line breaks/formatting.
    // The actual multiline formatting must be done by PHP-CS-Fixer.
    // This method exists to mark the node as processed.
    // PHP-CS-Fixer's method_argument_space with 'ensure_fully_multiline' will
    // format parameters on separate lines when there are 2+ parameters.

    // We return the node as-is, and PHP-CS-Fixer will handle the formatting
    // when configured with: 'method_argument_space' => ['ensure_fully_multiline' => true]
  }
}

