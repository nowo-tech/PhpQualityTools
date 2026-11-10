<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * One layer rule of the `layers.rules` list (see {@see LayerConfig}).
 *
 * Source files: the files of the declared contexts whose layer is in `in`, or (with `not_in`) whose layer is not in
 * `not_in` (files outside every layer of their context included); optionally only in the listed `contexts`.
 *
 * - `forbid_layers`: the files must not reference a name of the `to` layers (of any context, or `scope` same / other).
 * - `allow_layers`:  the files may only reference names of the `to` layers (and their own layer); names outside every
 *   layer (vendor code, not yet layered code) are not checked.
 * - `forbid_names`:  the files must not reference the `names` (exact, namespace prefix ending in `\`, or `*` glob),
 *   except the `allow` entries (same syntax).
 * - `forbid_calls`:  the files must not call the `methods` (`->name(` / `?->name(`, case-insensitive).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class LayerRule
{
    public const TYPES = ['forbid_layers', 'allow_layers', 'forbid_names', 'forbid_calls'];

    public const SCOPES = ['any', 'same', 'other'];

    /**
     * @param list<string> $in       Source layers (empty with `not_in`)
     * @param list<string> $notIn    Excluded source layers (empty with `in`)
     * @param list<string> $contexts Restrict to these contexts (empty = every declared context)
     * @param list<string> $to       Target layers (forbid_layers / allow_layers)
     * @param list<string> $names    Forbidden name patterns (forbid_names)
     * @param list<string> $allow    Allowed name patterns (forbid_names)
     * @param list<string> $methods  Forbidden method names, lowercased (forbid_calls)
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $in = [],
        public array $notIn = [],
        public array $contexts = [],
        public array $to = [],
        public string $scope = 'any',
        public array $names = [],
        public array $allow = [],
        public array $methods = [],
        public string $message = '',
    ) {
    }

    /**
     * Whether a file of `$context` in `$layer` (null = outside every layer) is checked by this rule.
     */
    public function appliesTo(string $context, ?string $layer): bool
    {
        if ([] !== $this->contexts && !\in_array($context, $this->contexts, true)) {
            return false;
        }
        if ([] !== $this->in) {
            return null !== $layer && \in_array($layer, $this->in, true);
        }

        return null === $layer || !\in_array($layer, $this->notIn, true);
    }

    /**
     * Name pattern match (case-insensitive): exact name, namespace prefix (`Symfony\`), or `*` / `?` glob over the
     * whole name (`*` also matches `\`).
     */
    public static function nameMatches(string $name, string $pattern): bool
    {
        $name = strtolower($name);
        $pattern = strtolower($pattern);
        if (false !== strpbrk($pattern, '*?')) {
            $pattern = (string) preg_replace('/\*+/', '*', $pattern); // `a**b` = `a*b`, and fewer backtracking paths
            $regex = '~^' . strtr(preg_quote($pattern, '~'), ['\*' => '.*', '\?' => '.']) . '$~';

            return 1 === preg_match($regex, $name);
        }
        if (str_ends_with($pattern, '\\')) {
            return str_starts_with($name, $pattern) || $name === rtrim($pattern, '\\');
        }

        return $name === $pattern;
    }

    /**
     * @param list<string> $patterns
     */
    public static function nameMatchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::nameMatches($name, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
