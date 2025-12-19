<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rector rule to format long grouped imports with multiline format.
 *
 * This rule detects grouped imports (e.g., `use App\Entity\Chat\{Conversation, UserConversation};`)
 * that exceed a specified character length and formats them with multiline format,
 * keeping them grouped but with each item on a separate line.
 *
 * Example:
 * - Before: `use App\Entity\Chat\{Conversation, UserConversation};`
 * - After:
 *   `use App\Entity\Chat\{
 *       Conversation,
 *       UserConversation
 *   };`
 */
final class SplitLongGroupedImportsRector extends AbstractRector
{
    /**
     * Maximum line length for grouped imports before splitting.
     *
     * Common standards:
     * - 80: Very strict (classic, but restrictive for modern code)
     * - 100: Balanced (good readability)
     * - 120: Modern standard (recommended for Symfony projects with long namespaces)
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
            'Format long grouped imports with multiline format when they exceed ' . self::MAX_LINE_LENGTH . ' characters',
            [
            new CodeSample(
                <<<'PHP'
                    use App\Entity\Chat\{Conversation, UserConversation, ChatMessage, ChatParticipant};
                    PHP,
                <<<'PHP'
                    use App\Entity\Chat\{
                        Conversation,
                        UserConversation,
                        ChatMessage,
                        ChatParticipant
                    };
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
        return [Use_::class, GroupUse::class];
    }

    /**
     * Refactor the node if it matches the criteria.
     *
     * @param Use_|GroupUse $node
     *
     * @return Node|array|null
     */
    public function refactor(Node $node): Node|array|null
    {
        // Process GroupUse nodes (grouped imports like use App\Entity\{Class1, Class2};)
        if ($node instanceof GroupUse) {
            // Check if this grouped import has multiple uses
            if (count($node->uses) <= 1) {
                return null;
            }

            // Calculate the length of the grouped import line
            $groupedImportLength = $this->calculateGroupedImportLength($node);

            // Format imports that exceed MAX_LINE_LENGTH OR have 3+ items
            // This ensures readability even for shorter imports with many items
            if ($groupedImportLength > self::MAX_LINE_LENGTH || count($node->uses) >= 3) {
                // Return the node as-is - the formatter will handle multiline formatting
                // We're just marking it for formatting
                return $node;
            }

            return null;
        }

        // Process Use_ nodes with multiple uses (less common, but possible)
        if ($node instanceof Use_) {
            // Check if this is a grouped import (has multiple uses in one statement)
            if (count($node->uses) <= 1) {
                return null;
            }

            // Calculate the length of the grouped import line
            $groupedImportLength = $this->calculateGroupedImportLengthForUse($node);

            // Format imports that exceed MAX_LINE_LENGTH OR have 3+ items
            // This ensures readability even for shorter imports with many items
            if ($groupedImportLength > self::MAX_LINE_LENGTH || count($node->uses) >= 3) {
                // Try to convert to GroupUse if there's a common prefix
                $formatted = $this->formatGroupedImportFromUseMultiline($node);
                if ($formatted !== null) {
                    return $formatted;
                }

                // If conversion fails, return the node as-is
                return $node;
            }

            return null;
        }

        return null;
    }

    /**
     * Calculate the approximate length of a GroupUse import statement.
     */
    private function calculateGroupedImportLength(GroupUse $node): int
    {
        $length = 0;

        // Add length for "use " prefix
        $length += 4;

        // Add length for the namespace prefix (before the curly braces)
        $prefix = $node->prefix->toString();
        $length += strlen($prefix);
        $length += 2; // For the { }

        // Add length for each use item
        foreach ($node->uses as $use) {
            $name = $use->name->toString();
            $length += strlen($name);
            $length += 2; // For comma and space

            // Add length for alias if present
            if ($use->alias !== null) {
                $length += strlen($use->alias->name) + 4; // " as " + alias name
            }
        }

        // Subtract 2 for the last item (no comma)
        $length -= 2;

        return $length;
    }

    /**
     * Calculate the approximate length of a Use_ import statement with multiple uses.
     */
    private function calculateGroupedImportLengthForUse(Use_ $node): int
    {
        $length = 0;

        // Add length for "use " prefix
        $length += 4;

        // Get the common namespace prefix (if all uses share the same prefix)
        $prefix = $this->getCommonPrefix($node->uses);

        if ($prefix !== '') {
            $length += strlen($prefix);
            $length += 2; // For the { }
        }

        // Add length for each use item
        foreach ($node->uses as $use) {
            $name = $this->getName($use);
            if ($name !== null) {
                // If there's a prefix, only count the part after the prefix
                if ($prefix !== '' && str_starts_with($name, $prefix)) {
                    $suffix = substr($name, strlen($prefix));
                    $length += strlen($suffix);
                } else {
                    $length += strlen($name);
                }
                $length += 2; // For comma and space
            }

            // Add length for alias if present
            if ($use->alias !== null) {
                $length += strlen($use->alias->name) + 4; // " as " + alias name
            }
        }

        // Subtract 2 for the last item (no comma)
        $length -= 2;

        return $length;
    }

    /**
     * Get the common namespace prefix from a list of use statements.
     *
     * @param UseUse[] $uses
     */
    private function getCommonPrefix(array $uses): string
    {
        if (empty($uses)) {
            return '';
        }

        $names = [];
        foreach ($uses as $use) {
            $name = $this->getName($use);
            if ($name !== null) {
                $names[] = $name;
            }
        }

        if (empty($names)) {
            return '';
        }

        // Find the common prefix
        $first = $names[0];
        $prefix = '';

        // Find the last namespace separator that all names share
        $parts = explode('\\', $first);
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $testPrefix = implode('\\', array_slice($parts, 0, $i + 1)) . '\\';
            $allMatch = true;

            foreach ($names as $name) {
                if (!str_starts_with($name, $testPrefix)) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                $prefix = $testPrefix;
            } else {
                break;
            }
        }

        return $prefix;
    }

    /**
     * Format a GroupUse import with multiline format.
     *
     * Simply returns the node as-is. The actual formatting will be handled by PHP-CS-Fixer
     * when configured with the appropriate rules for grouped imports.
     * This rule just marks which imports need to be formatted multiline.
     *
     * @return GroupUse
     */
    private function formatGroupedImportMultiline(GroupUse $node): GroupUse
    {
        // Return the node as-is - PHP-CS-Fixer will handle the multiline formatting
        // when the grouped import exceeds the line length
        return $node;
    }

    /**
     * Format a Use_ import with multiple uses with multiline format.
     *
     * Converts it to a GroupUse with multiline format.
     *
     * @return GroupUse|null
     */
    private function formatGroupedImportFromUseMultiline(Use_ $node): ?GroupUse
    {
        // Get the common prefix
        $prefix = $this->getCommonPrefix($node->uses);

        if ($prefix === '') {
            // Can't convert to GroupUse without a common prefix
            return null;
        }

        // Remove the trailing backslash from prefix
        $prefix = rtrim($prefix, '\\');
        $prefixParts = explode('\\', $prefix);
        $prefixNode = new Name($prefixParts);

        $formattedUses = [];

        foreach ($node->uses as $index => $use) {
            $name = $this->getName($use);
            if ($name === null) {
                continue;
            }

            // Remove the prefix from the name
            $suffix = substr($name, strlen($prefix) + 1);
            $suffixParts = explode('\\', $suffix);
            $suffixNode = new Name($suffixParts);

            $formattedUses[] = new UseUse(
                $suffixNode,
                $use->alias,
                $use->type,
                $use->getAttributes()
            );
        }

        // Create a GroupUse with formatted uses
        return new GroupUse(
            $prefixNode,
            $formattedUses,
            $node->type,
            $node->getAttributes()
        );
    }
}
