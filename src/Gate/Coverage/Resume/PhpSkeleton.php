<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * The shape of a PHP file without its function bodies, for the coverage resume (`bin/coverage-resume`).
 *
 * A source change can be resumed only when it stays inside function bodies: the skeleton (every token outside a
 * function body except whitespace and `//`/`#` comments; doc comments and attributes count) must be unchanged.
 * Then no signature, constant, property default, attribute, `extends` / `implements`, trait use or import changed,
 * so only the code that runs when a function is called changed, and every test that called it executed one of the
 * file's lines in the kept run.
 *
 * Each outermost function body (a method, a named function or a closure outside any other body) is recorded with its
 * line range and a hash of its tokens; a body containing `yield` is marked as a generator in the skeleton, so turning
 * a function into a generator (or back) is not a body-only change.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class PhpSkeleton
{
    /**
     * @param string                                 $skeleton sha1 of the tokens outside function bodies
     * @param list<array{0: int, 1: int, 2: string}> $bodies   [first line, last line, sha1 of the body tokens]
     * @param list<string>                           $declares short names of the classes, interfaces, traits and enums declared
     * @param list<string>                           $parents  short names after `extends` / `implements` and of the traits used
     */
    public function __construct(
        public string $skeleton,
        public array $bodies,
        public array $declares,
        public array $parents,
    ) {
    }

    public static function analyse(string $code): self
    {
        $tokens = array_values(\PhpToken::tokenize($code));
        $count = \count($tokens);
        $parts = [];
        $bodies = [];
        $declares = [];
        $parents = [];
        /** @var list<int> $pending paren depth of each function keyword whose body has not started yet */
        $pending = [];
        $parenDepth = 0;
        $braceDepth = 0;
        $collect = null; // 'parents' while reading an extends / implements / trait-use list
        $previous = null;
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if ($token->isIgnorable() && !$token->is(\T_DOC_COMMENT)) {
                continue;
            }
            $text = $token->text;
            if ('{' === $text && [] !== $pending && end($pending) === $parenDepth) {
                array_pop($pending);
                [$end, $hash, $generator] = self::body($tokens, $i);
                $bodies[] = [$token->line, $tokens[$end]->line, $hash];
                $parts[] = $generator ? '{BODY:generator}' : '{BODY}';
                $i = $end;
                $previous = null;
                continue;
            }
            $parts[] = $text;
            if ($token->is(\T_FUNCTION)) {
                $pending[] = $parenDepth;
            } elseif ('(' === $text) {
                ++$parenDepth;
            } elseif (')' === $text) {
                --$parenDepth;
            } elseif (';' === $text) {
                if ([] !== $pending && end($pending) === $parenDepth) {
                    array_pop($pending);
                }
                $collect = null;
            } elseif ('{' === $text || $token->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
                ++$braceDepth;
                $collect = null;
            } elseif ('}' === $text) {
                --$braceDepth;
            } elseif ($token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && !self::isClassConstant($previous)) {
                $name = self::nextName($tokens, $i);
                if (null !== $name) {
                    $declares[] = $name;
                }
            } elseif ($token->is([\T_EXTENDS, \T_IMPLEMENTS]) || ($token->is(\T_USE) && $braceDepth > 0)) {
                $collect = 'parents';
            } elseif (null !== $collect && $token->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])) {
                $parents[] = self::shortName($text);
            }
            $previous = $token;
        }

        return new self(
            sha1(implode("\0", $parts)),
            $bodies,
            array_values(array_unique($declares)),
            array_values(array_unique($parents)),
        );
    }

    /**
     * The body that opens at $open: index of its closing brace, sha1 of its tokens (whitespace and comments left out)
     * and whether it contains `yield`.
     *
     * @param list<\PhpToken> $tokens
     *
     * @return array{0: int, 1: string, 2: bool}
     */
    private static function body(array $tokens, int $open): array
    {
        $depth = 0;
        $parts = [];
        $generator = false;
        $count = \count($tokens);
        for ($i = $open; $i < $count; ++$i) {
            $token = $tokens[$i];
            if ('{' === $token->text || $token->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
                ++$depth;
            } elseif ('}' === $token->text) {
                --$depth;
            }
            if ($token->is([\T_YIELD, \T_YIELD_FROM])) {
                $generator = true;
            }
            if (!$token->isIgnorable()) {
                $parts[] = $token->text;
            }
            if (0 === $depth) {
                break;
            }
        }

        // An unterminated body (a parse error) runs to the end of the file.
        return [min($i, $count - 1), sha1(implode("\0", $parts)), $generator];
    }

    private static function isClassConstant(?\PhpToken $previous): bool
    {
        return $previous instanceof \PhpToken && ($previous->is(\T_DOUBLE_COLON) || $previous->is(\T_NEW));
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private static function nextName(array $tokens, int $index): ?string
    {
        $count = \count($tokens);
        for ($i = $index + 1; $i < $count; ++$i) {
            if ($tokens[$i]->isIgnorable()) {
                continue;
            }

            return $tokens[$i]->is(\T_STRING) ? $tokens[$i]->text : null;
        }

        return null;
    }

    private static function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return false === $position ? $name : substr($name, $position + 1);
    }
}
