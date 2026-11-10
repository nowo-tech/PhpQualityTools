<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Token-based dependency scan for the layer rules: the names a PHP file references, resolved to fully-qualified names,
 * and its method calls.
 *
 * Names:
 * - every top-level `use` import (also grouped `use A\{B, C as D}`, `use function`, `use const`; aliases are read);
 * - every fully-qualified name in the code (`\App\Foo`, `\App\Foo::class`, attributes, type hints);
 * - every qualified name, resolved: through an imported alias when its first segment is one (`use Doctrine\ORM;` +
 *   `ORM\EntityManagerInterface` is `Doctrine\ORM\EntityManagerInterface`), else against the namespace
 *   (`Infrastructure\Foo::class` inside `namespace App\Uptime;` is `App\Uptime\Infrastructure\Foo`), and every
 *   `namespace\Foo` relative name.
 *
 * Unqualified names (`Foo::class`, `new Foo()`) are not recorded: they are the file's own namespace or an import that
 * is already recorded. Comments, strings and heredocs are never read.
 *
 * Calls: every `->method(` / `?->method(` (method name lowercased).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ReferenceExtractor
{
    private const NAME_TOKENS = [\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED];

    /**
     * @return array{names: list<array{name: string, line: int}>, calls: list<array{method: string, line: int}>}
     */
    public function extract(string $code): array
    {
        // No TOKEN_PARSE: it raises uncatchable compile errors (duplicate modifiers, newer syntax on an older PHP), and
        // nothing below needs it (names are T_NAME_* and a label after `->` is T_STRING in both modes).
        $tokens = token_get_all($code);

        $significant = [];
        foreach ($tokens as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $significant[] = $token;
        }

        $names = [];
        $calls = [];
        $namespace = '';
        /** @var array<string, string> $aliases lowercased alias => fully-qualified name (class imports only) */
        $aliases = [];
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
            switch ($token[0]) {
                case \T_CURLY_OPEN:
                case \T_DOLLAR_OPEN_CURLY_BRACES:
                    $braces[] = false;
                    break;

                case \T_NAMESPACE:
                    $namespaceBlockPending = true;
                    $next = $significant[$i + 1] ?? null;
                    $namespace = \is_array($next) && \in_array($next[0], [\T_STRING, \T_NAME_QUALIFIED], true) ? $next[1] : '';
                    $aliases = [];
                    if ('' !== $namespace) {
                        ++$i;
                    }
                    break;

                case \T_NAME_FULLY_QUALIFIED:
                    $names[] = ['name' => ltrim($token[1], '\\'), 'line' => $token[2]];
                    break;

                case \T_NAME_RELATIVE: // namespace\Foo
                    $names[] = ['name' => $this->join($namespace, substr($token[1], \strlen('namespace\\'))), 'line' => $token[2]];
                    break;

                case \T_NAME_QUALIFIED:
                    $first = (string) strstr($token[1], '\\', true);
                    $names[] = [
                        'name' => isset($aliases[strtolower($first)])
                            ? $aliases[strtolower($first)] . substr($token[1], \strlen($first)) // `use Doctrine\ORM;` + ORM\EntityManagerInterface
                            : $this->join($namespace, $token[1]),
                        'line' => $token[2],
                    ];
                    break;

                case \T_OBJECT_OPERATOR:
                case \T_NULLSAFE_OBJECT_OPERATOR:
                    $method = $significant[$i + 1] ?? null;
                    if (\is_array($method) && \T_STRING === $method[0] && '(' === ($significant[$i + 2] ?? null)) {
                        $calls[] = ['method' => strtolower($method[1]), 'line' => $method[2]];
                        ++$i;
                    }
                    break;

                case \T_USE:
                    if (\in_array(false, $braces, true)) {
                        break; // trait use inside a class body: its names are read as ordinary names
                    }
                    $previous = $significant[$i - 1] ?? null;
                    if (')' === $previous || '(' === ($significant[$i + 1] ?? null)) {
                        break; // closure use (...)
                    }
                    $i = $this->readUse($significant, $i + 1, $names, $aliases);
                    break;
            }
        }

        return ['names' => $names, 'calls' => $calls];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @param list<array{name: string, line: int}>          $names
     * @param array<string, string>                         $aliases
     *
     * @return int index of the terminating `;`
     */
    private function readUse(array $tokens, int $i, array &$names, array &$aliases): int
    {
        $prefix = '';
        $statementKind = 'class'; // `use function` / `use const` for the whole statement
        $kind = 'class';          // kind of the current entry (a mixed group may hold `function` / `const` entries)
        $last = null;             // index in $names of the name waiting for its alias
        $count = \count($tokens);
        for (; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                if (';' === $token) {
                    break;
                }
                if (',' === $token || '{' === $token || '}' === $token) {
                    $this->alias($names, $last, null, $kind, $aliases);
                    $last = null;
                    $kind = $statementKind;
                    if ('}' === $token) {
                        $prefix = '';
                    }
                }
                continue;
            }
            if (\T_FUNCTION === $token[0] || \T_CONST === $token[0]) {
                $kind = 'other';
                if ('' === $prefix && null === $last) {
                    $statementKind = 'other';
                }
                continue;
            }
            if (\T_AS === $token[0]) {
                $alias = $tokens[$i + 1] ?? null;
                if (\is_array($alias) && \T_STRING === $alias[0]) {
                    $this->alias($names, $last, $alias[1], $kind, $aliases);
                    $last = null;
                    ++$i;
                }
                continue;
            }
            if (!\in_array($token[0], self::NAME_TOKENS, true) || null !== $last) {
                continue;
            }
            $name = ltrim($token[1], '\\');
            $after = $tokens[$i + 1] ?? null;
            if (\is_array($after) && \T_NS_SEPARATOR === $after[0] && '{' === ($tokens[$i + 2] ?? null)) {
                $prefix = $name . '\\';
                ++$i;
                continue;
            }
            $names[] = ['name' => $prefix . $name, 'line' => $token[2]];
            $last = array_key_last($names);
        }
        $this->alias($names, $last, null, $kind, $aliases);

        return $i;
    }

    /**
     * @param list<array{name: string, line: int}> $names
     * @param array<string, string>                $aliases
     */
    private function alias(array $names, ?int $index, ?string $alias, string $kind, array &$aliases): void
    {
        if (null === $index || 'class' !== $kind) {
            return;
        }
        $name = $names[$index]['name'];
        $short = $alias ?? (false === ($pos = strrpos($name, '\\')) ? $name : substr($name, $pos + 1));
        $aliases[strtolower($short)] = $name;
    }

    private function join(string $namespace, string $name): string
    {
        return '' === $namespace ? $name : $namespace . '\\' . $name;
    }
}
