<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\PhpCsFixer\Rules;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Fixer to format long arrays with multiline format.
 *
 * This fixer detects arrays that exceed a specified character length
 * or have a minimum number of elements and formats them with multiline format.
 *
 * Example:
 * - Before: `$data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];`
 * - After:
 *   `$data = [
 *       'key1' => 'value1',
 *       'key2' => 'value2',
 *       'key3' => 'value3',
 *   ];`
 */
final class MultilineArrayFixer extends AbstractFixer
{
    /**
     * Maximum line length for arrays before formatting multiline.
     */
    private const MAX_LINE_LENGTH = 120;

    /**
     * Minimum number of elements to force multiline format.
     */
    private const MIN_ELEMENTS = 3;

    /**
     * Get the fixer definition.
     */
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Format long arrays with multiline format when they exceed ' . self::MAX_LINE_LENGTH . ' characters or have ' . self::MIN_ELEMENTS . '+ elements.',
            [
                new CodeSample(
                    <<<'PHP'
<?php
$data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
PHP
                ),
            ]
        );
    }

    /**
     * Get the name of the fixer.
     */
    public function getName(): string
    {
        return 'NowoTech/multiline_array';
    }

    /**
     * Get the priority of this fixer.
     */
    public function getPriority(): int
    {
        return -10; // Run before other array fixers
    }

    /**
     * Check if the fixer is a candidate for a given token.
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(CT::T_ARRAY_SQUARE_BRACE_OPEN) || $tokens->isTokenKindFound(T_ARRAY);
    }

    /**
     * Check if the fixer supports a given file.
     */
    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * Apply the fix.
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = $tokens->count() - 1; $index > 0; --$index) {
            $token = $tokens[$index];

            // Find array opening (short syntax [] or long syntax array())
            $arrayStartIndex = null;
            if ($token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                $arrayStartIndex = $index;
            } elseif ($token->isGivenKind(T_ARRAY) && $tokens[$index + 1]->equals('(')) {
                $arrayStartIndex = $index;
            }

            if ($arrayStartIndex === null) {
                continue;
            }

            $arrayEndIndex = $this->findArrayEnd($tokens, $arrayStartIndex);

            if ($arrayEndIndex === null) {
                continue;
            }

            // Check if already multiline
            if ($this->isAlreadyMultiline($tokens, $arrayStartIndex, $arrayEndIndex)) {
                continue;
            }

            // Count elements
            $elementCount = $this->countArrayElements($tokens, $arrayStartIndex, $arrayEndIndex);

            // Calculate length
            $length = $this->calculateArrayLength($tokens, $arrayStartIndex, $arrayEndIndex);

            // Format if too long or has enough elements
            if ($length > self::MAX_LINE_LENGTH || $elementCount >= self::MIN_ELEMENTS) {
                $this->formatMultiline($tokens, $arrayStartIndex, $arrayEndIndex);
            }
        }
    }

    /**
     * Find the end of an array.
     */
    private function findArrayEnd(Tokens $tokens, int $startIndex): ?int
    {
        $token = $tokens[$startIndex];
        $depth = 0;
        $index = $startIndex;

        // Handle short syntax []
        if ($token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
            while ($index < $tokens->count()) {
                $token = $tokens[$index];
                if ($token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                    $depth++;
                } elseif ($token->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_CLOSE)) {
                    $depth--;
                    if ($depth === 0) {
                        return $index;
                    }
                }
                $index++;
            }
        }
        // Handle long syntax array()
        elseif ($token->isGivenKind(T_ARRAY) && $tokens[$startIndex + 1]->equals('(')) {
            $index = $startIndex + 1; // Start after 'array'
            while ($index < $tokens->count()) {
                $token = $tokens[$index];
                if ($token->equals('(')) {
                    $depth++;
                } elseif ($token->equals(')')) {
                    $depth--;
                    if ($depth === 0) {
                        return $index;
                    }
                }
                $index++;
            }
        }

        return null;
    }

    /**
     * Check if array is already multiline.
     */
    private function isAlreadyMultiline(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        $startLine = $tokens[$startIndex]->getLine();
        $endLine = $tokens[$endIndex]->getLine();

        return $endLine > $startLine;
    }

    /**
     * Count array elements.
     */
    private function countArrayElements(Tokens $tokens, int $startIndex, int $endIndex): int
    {
        $count = 0;
        $depth = 0;
        $startToken = $tokens[$startIndex];
        
        // Skip opening bracket/parenthesis
        $i = $startIndex + 1;
        if ($startToken->isGivenKind(T_ARRAY) && $tokens[$i]->equals('(')) {
            $i++; // Skip '('
        }

        for (; $i < $endIndex; $i++) {
            $token = $tokens[$i];

            if ($token->isGivenKind([T_ARRAY, CT::T_ARRAY_SQUARE_BRACE_OPEN]) || $token->equals('(')) {
                $depth++;
            } elseif ($token->isGivenKind([CT::T_ARRAY_SQUARE_BRACE_CLOSE]) || $token->equals(')')) {
                $depth--;
            } elseif ($depth === 0 && $token->equals(',')) {
                $count++;
            }
        }

        return $count + 1; // +1 for last element
    }

    /**
     * Calculate array length.
     */
    private function calculateArrayLength(Tokens $tokens, int $startIndex, int $endIndex): int
    {
        $length = 0;

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $token = $tokens[$i];
            $length += strlen($token->getContent());
        }

        return $length;
    }

    /**
     * Format array as multiline.
     */
    private function formatMultiline(Tokens $tokens, int $startIndex, int $endIndex): void
    {
        // Insert newline after opening bracket
        $tokens->insertAt($startIndex + 1, new Token([T_WHITESPACE, "\n    "]));

        // Format each element on a new line
        $depth = 0;
        for ($i = $startIndex + 2; $i < $endIndex; $i++) {
            $token = $tokens[$i];

            if ($token->isGivenKind([T_ARRAY, CT::T_ARRAY_SQUARE_BRACE_OPEN])) {
                $depth++;
            } elseif ($token->isGivenKind([CT::T_ARRAY_SQUARE_BRACE_CLOSE])) {
                $depth--;
            } elseif ($depth === 0 && $token->equals(',')) {
                // Insert newline after comma
                $nextIndex = $i + 1;
                if ($nextIndex < $tokens->count() && !$tokens[$nextIndex]->isWhitespace()) {
                    $tokens->insertAt($nextIndex, new Token([T_WHITESPACE, "\n    "]));
                } elseif ($nextIndex < $tokens->count()) {
                    $tokens[$nextIndex] = new Token([T_WHITESPACE, "\n    "]);
                }
            }
        }

        // Insert newline before closing bracket
        if ($endIndex > 0 && !$tokens[$endIndex - 1]->isWhitespace()) {
            $tokens->insertAt($endIndex, new Token([T_WHITESPACE, "\n"]));
        }
    }
}

