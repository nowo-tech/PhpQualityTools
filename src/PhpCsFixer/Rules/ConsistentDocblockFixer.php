<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\PhpCsFixer\Rules;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Fixer to ensure consistent docblock formatting.
 *
 * This fixer ensures that docblocks follow a consistent format:
 * - Single line docblocks for simple cases
 * - Multiline docblocks with proper alignment
 * - Consistent spacing
 *
 * Example:
 * - Before:
 *
 *   `/** @var string *\/`
 * - After:
 *   `/** @var string *\/`
 */
final class ConsistentDocblockFixer extends AbstractFixer
{
    /**
     * Get the fixer definition.
     */
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Ensure consistent docblock formatting with proper alignment and spacing.',
            [
                new CodeSample(
                    <<<'PHP'
                        <?php
                        class Example
                        {
                            /** @var string */
                            private $name;
                            
                            /**
                             * @param string $name
                             * @return void
                             */
                            public function setName($name) {}
                        }
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
        return 'NowoTech/consistent_docblock';
    }

    /**
     * Get the priority of this fixer.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Check if the fixer is a candidate for a given token.
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_DOC_COMMENT);
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

            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            $content = $token->getContent();
            $formatted = $this->formatDocblock($content);

            if ($formatted !== $content) {
                $tokens[$index] = new Token([T_DOC_COMMENT, $formatted]);
            }
        }
    }

    /**
     * Format a docblock content.
     */
    private function formatDocblock(string $content): string
    {
        // Remove leading/trailing whitespace
        $content = trim($content);

        // If it's a single line docblock, ensure proper format
        if (preg_match('/^\/\*\* (.+) \*\/$/', $content, $matches)) {
            return '/** ' . trim($matches[1]) . ' */';
        }

        // For multiline docblocks, ensure consistent formatting
        $lines = explode("\n", $content);
        $formatted = [];

        foreach ($lines as $line) {
            $line = rtrim($line);

            // Skip empty lines at start/end
            if (empty($line) && (empty($formatted) || end($formatted) === ' */')) {
                continue;
            }

            // Ensure proper alignment
            if (str_starts_with($line, ' *')) {
                $formatted[] = $line;
            } elseif (str_starts_with($line, '/**')) {
                $formatted[] = '/**';
            } elseif (str_starts_with($line, ' */')) {
                $formatted[] = ' */';
            } else {
                // Add * prefix if missing
                $formatted[] = ' * ' . ltrim($line, ' *');
            }
        }

        return implode("\n", $formatted);
    }
}
