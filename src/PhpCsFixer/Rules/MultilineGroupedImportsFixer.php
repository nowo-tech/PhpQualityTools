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
 * Fixer to format long grouped imports with multiline format.
 *
 * This fixer detects grouped imports (e.g., `use App\Entity\Chat\{Conversation, UserConversation};`)
 * that exceed a specified character length or have 3+ items and formats them with multiline format,
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
final class MultilineGroupedImportsFixer extends AbstractFixer
{
  /**
   * Maximum line length for grouped imports before formatting multiline.
   * Default: 120 characters
   */
  private const MAX_LINE_LENGTH = 120;

  /**
   * Get the fixer definition.
   */
  public function getDefinition(): FixerDefinitionInterface
  {
    return new FixerDefinition(
      'Format long grouped imports with multiline format when they exceed ' . self::MAX_LINE_LENGTH . ' characters or have 3+ items.',
      [
        new CodeSample(
          <<<'PHP'
use App\Entity\Chat\{Conversation, UserConversation};
use App\Service\Chat\{ChatManagement, ChatQuery};
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
    return 'NowoTech/multiline_grouped_imports';
  }

  /**
   * Check if the fixer is a candidate for a given token.
   */
  public function isCandidate(Tokens $tokens): bool
  {
    return $tokens->isTokenKindFound(T_USE);
  }

  /**
   * Get the priority of this fixer.
   * Should run after group_import but before other import-related fixers.
   */
  public function getPriority(): int
  {
    return -5;
  }

  /**
   * Apply the fix.
   */
  protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
  {
    for ($index = $tokens->count() - 1; $index > 0; --$index) {
      if (!$tokens[$index]->isGivenKind(T_USE)) {
        continue;
      }

      $useStartIndex = $index;
      $useEndIndex = $this->findUseEndIndex($tokens, $useStartIndex);

      if ($useEndIndex === null) {
        continue;
      }

      // Check if this is a grouped import (has { })
      $groupStartIndex = null;
      $groupEndIndex = null;

      for ($i = $useStartIndex; $i < $useEndIndex; ++$i) {
        if ($tokens[$i]->equals('{')) {
          $groupStartIndex = $i;
          // Find matching closing brace
          $braceDepth = 1;
          for ($j = $i + 1; $j < $useEndIndex; ++$j) {
            if ($tokens[$j]->equals('{')) {
              ++$braceDepth;
            } elseif ($tokens[$j]->equals('}')) {
              --$braceDepth;
              if ($braceDepth === 0) {
                $groupEndIndex = $j;
                break;
              }
            }
          }
          break;
        }
      }

      if ($groupStartIndex === null || $groupEndIndex === null) {
        continue;
      }

      // Calculate the length of the grouped import line
      $lineLength = $this->calculateLineLength($tokens, $useStartIndex, $useEndIndex);
      $itemCount = $this->countItemsInGroup($tokens, $groupStartIndex, $groupEndIndex);

      // Format if line is too long OR has 3+ items
      if ($lineLength > self::MAX_LINE_LENGTH || $itemCount >= 3) {
        $this->formatMultiline($tokens, $groupStartIndex, $groupEndIndex);
      }
    }
  }

  /**
   * Find the end index of a use statement.
   */
  private function findUseEndIndex(Tokens $tokens, int $startIndex): ?int
  {
    $index = $startIndex;
    $depth = 0;

    while ($index < $tokens->count()) {
      $token = $tokens[$index];

      if ($token->equals('{')) {
        ++$depth;
      } elseif ($token->equals('}')) {
        --$depth;
      }

      if ($token->equals(';') && $depth === 0) {
        return $index;
      }

      ++$index;
    }

    return null;
  }

  /**
   * Calculate the length of a use statement line.
   */
  private function calculateLineLength(Tokens $tokens, int $startIndex, int $endIndex): int
  {
    $length = 0;

    for ($i = $startIndex; $i <= $endIndex; ++$i) {
      $token = $tokens[$i];
      $content = $token->getContent();

      // Count actual characters, not token length
      $length += \strlen($content);
    }

    return $length;
  }

  /**
   * Count the number of items in a grouped import.
   */
  private function countItemsInGroup(Tokens $tokens, int $groupStartIndex, int $groupEndIndex): int
  {
    $count = 0;

    for ($i = $groupStartIndex + 1; $i < $groupEndIndex; ++$i) {
      $token = $tokens[$i];

      if ($token->equals(',')) {
        ++$count;
      }
    }

    // Add 1 for the last item (no comma)
    return $count + 1;
  }

  /**
   * Format a grouped import with multiline format.
   */
  private function formatMultiline(Tokens $tokens, int $groupStartIndex, int $groupEndIndex): void
  {
    // Check if already formatted multiline
    if ($groupStartIndex + 1 < $tokens->count()) {
      $nextToken = $tokens[$groupStartIndex + 1];
      if ($nextToken->isWhitespace() && str_contains($nextToken->getContent(), "\n")) {
        return; // Already formatted
      }
    }

    // Insert newline and indent after opening brace
    $indent = '    ';
    if ($groupStartIndex + 1 < $tokens->count() && !$tokens[$groupStartIndex + 1]->isWhitespace()) {
      $tokens->insertAt($groupStartIndex + 1, new Token([T_WHITESPACE, "\n" . $indent]));
    } elseif ($groupStartIndex + 1 < $tokens->count()) {
      // Replace existing whitespace
      $tokens[$groupStartIndex + 1] = new Token([T_WHITESPACE, "\n" . $indent]);
    }

    // Format each comma-separated item
    // We need to work backwards to avoid index shifting issues
    $commas = [];
    for ($i = $groupStartIndex + 1; $i < $groupEndIndex; ++$i) {
      if ($tokens[$i]->equals(',')) {
        $commas[] = $i;
      }
    }

    // Insert newlines after commas (working backwards to preserve indices)
    foreach (array_reverse($commas) as $commaIndex) {
      // Check if there's already a newline after the comma
      $nextIndex = $commaIndex + 1;
      if ($nextIndex < $tokens->count()) {
        $nextToken = $tokens[$nextIndex];
        if ($nextToken->isWhitespace() && str_contains($nextToken->getContent(), "\n")) {
          // Replace with properly indented newline
          $tokens[$nextIndex] = new Token([T_WHITESPACE, "\n" . $indent]);
        } elseif (!$nextToken->isWhitespace()) {
          // Insert newline and indent
          $tokens->insertAt($nextIndex, new Token([T_WHITESPACE, "\n" . $indent]));
        }
      }
    }

    // Insert newline before closing brace
    if ($groupEndIndex > 0) {
      $prevIndex = $groupEndIndex - 1;
      if ($prevIndex >= 0 && !$tokens[$prevIndex]->isWhitespace()) {
        $tokens->insertAt($groupEndIndex, new Token([T_WHITESPACE, "\n"]));
      } elseif ($prevIndex >= 0 && $tokens[$prevIndex]->isWhitespace() && !str_contains($tokens[$prevIndex]->getContent(), "\n")) {
        $tokens[$prevIndex] = new Token([T_WHITESPACE, "\n"]);
      }
    }
  }
}

