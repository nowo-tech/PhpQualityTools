<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Extracts the class / function / constant names a PHP file depends on, with their line numbers.
 *
 * - Imports: top-level `use` statements (also grouped `use A\{B, C}` and `use function` / `use const`).
 *   Trait `use` inside class bodies and closure `use (...)` are not imports.
 * - With `$includeFullyQualified`, every fully-qualified name in the code (`\App\Foo\Bar`) too.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ImportExtractor
{
    private const NAME_TOKENS = [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED];

    /**
     * @return list<array{name: string, line: int}> Names without leading backslash, in source order
     */
    public function extract(string $code, bool $includeFullyQualified = false): array
    {
        try {
            $tokens = \token_get_all($code, \TOKEN_PARSE);
        } catch (\ParseError) {
            $tokens = \token_get_all($code);
        }

        $significant = [];
        foreach ($tokens as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $significant[] = $token;
        }

        $names = [];
        /** @var list<bool> $braces true = namespace block brace */
        $braces = [];
        $namespaceBlockPending = false;
        $count = \count($significant);
        for ($i = 0; $i < $count; ++$i) {
            $token = $significant[$i];
            if (!\is_array($token)) {
                if ('{' === $token) {
                    $braces[] = $namespaceBlockPending;
                    $namespaceBlockPending = false;
                } elseif ('}' === $token) {
                    array_pop($braces);
                } elseif (';' === $token) {
                    $namespaceBlockPending = false;
                }
                continue;
            }
            if (\T_CURLY_OPEN === $token[0] || \T_DOLLAR_OPEN_CURLY_BRACES === $token[0]) {
                $braces[] = false;
                continue;
            }
            if (\T_NAMESPACE === $token[0]) {
                $namespaceBlockPending = true;
                continue;
            }
            if (\T_NAME_FULLY_QUALIFIED === $token[0] && $includeFullyQualified) {
                $names[] = ['name' => \ltrim($token[1], '\\'), 'line' => $token[2]];
                continue;
            }
            if (\T_USE !== $token[0] || \in_array(false, $braces, true)) {
                continue;
            }
            $previous = $significant[$i - 1] ?? null;
            $next = $significant[$i + 1] ?? null;
            if (')' === $previous || '(' === $next) {
                continue; // closure use (...)
            }
            $i = $this->readUse($significant, $i + 1, $names);
        }

        return $names;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @param list<array{name: string, line: int}>          $names
     *
     * @return int index of the terminating `;`
     */
    private function readUse(array $tokens, int $i, array &$names): int
    {
        $prefix = '';
        $expectName = true;
        $count = \count($tokens);
        for (; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                if (';' === $token) {
                    return $i;
                }
                if (',' === $token || '{' === $token) {
                    $expectName = true;
                } elseif ('}' === $token) {
                    $prefix = '';
                }
                continue;
            }
            if (\T_FUNCTION === $token[0] || \T_CONST === $token[0] || \T_AS === $token[0]) {
                continue;
            }
            if (!$expectName || !\in_array($token[0], self::NAME_TOKENS, true)) {
                continue;
            }
            $name = \ltrim($token[1], '\\');
            $after = $tokens[$i + 1] ?? null;
            if (\is_array($after) && \T_NS_SEPARATOR === $after[0] && '{' === ($tokens[$i + 2] ?? null)) {
                $prefix = $name . '\\';
                ++$i;
                continue;
            }
            $names[] = ['name' => $prefix . $name, 'line' => $token[2]];
            $expectName = false;
        }

        return $i;
    }
}
