<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * One normalized entry of the `rules:` list of a module-boundary config.
 *
 * Types:
 * - `forbid_path`: none of `path` may exist (file or directory).
 * - `require_path`: every `path` must exist.
 * - `forbid_import`: no PHP file under `in` (minus `except`) may import one of `imports`
 *   (exact name, or namespace prefix when the entry ends with `\`).
 * - `forbid_pattern`: no PHP file under `in` (minus `except`) may match the regex `pattern`.
 * - `require_pattern`: every existing file in `path` must match `pattern` (missing files are skipped).
 * - `require_near`: in every existing file in `path`, each line matching `pattern` must have `requires`
 *   on the same line or within the next `within_lines` lines.
 *
 * Regexes are PCRE without delimiters, multi-line mode (`^` / `$` match at line boundaries).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class Rule
{
    public const TYPES = ['forbid_path', 'require_path', 'forbid_import', 'forbid_pattern', 'require_pattern', 'require_near'];

    /**
     * @param list<string> $paths    Files / directories (`path` key)
     * @param list<string> $in       Directories or files to scan (`in` key)
     * @param list<string> $except   Path patterns excluded from `in`
     * @param list<string> $imports  Forbidden import names / namespace prefixes
     * @param string|null  $pattern  Compiled regex (with delimiters)
     * @param string|null  $requires Compiled regex (with delimiters)
     */
    public function __construct(
        public string $type,
        public string $message,
        public array $paths = [],
        public array $in = [],
        public array $except = [],
        public array $imports = [],
        public ?string $pattern = null,
        public ?string $requires = null,
        public int $withinLines = 0,
    ) {
    }
}
