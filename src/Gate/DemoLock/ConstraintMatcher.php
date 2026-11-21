<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\DemoLock;

/**
 * Does an installed version satisfy a Composer constraint? Caret, tilde, comparison operators, `1.2.*` wildcards,
 * `||` / `|` alternatives and AND (space or comma separated) only: enough for the constraints of a bundle's
 * `require`, without depending on `composer/semver` (the gate also runs from a bare clone).
 *
 * `dev-*` versions and the constraints `*`, `@dev` and the empty one always match.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ConstraintMatcher
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if (str_starts_with($version, 'dev-') || \in_array($constraint, ['*', '@dev', ''], true)) {
            return true;
        }
        // ">= 1.0" and "^ 1.0" with a blank after the operator.
        $constraint = (string) preg_replace('/(>=|<=|>|<|=|\^|~)\s+/', '$1', $constraint);
        $installed = self::tuple($version);

        foreach (preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $alternative) {
            if (self::alternativeMatches($installed, $alternative)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{int, int, int} $installed
     */
    private static function alternativeMatches(array $installed, string $alternative): bool
    {
        foreach (preg_split('/[\s,]+/', trim($alternative)) ?: [] as $part) {
            $part = explode('@', $part)[0];
            if ('' === $part || '*' === $part) {
                continue;
            }
            if (!self::partMatches($installed, $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{int, int, int} $installed
     */
    private static function partMatches(array $installed, string $part): bool
    {
        if (str_starts_with($part, '^')) {
            $bound = substr($part, 1);
            $low = self::tuple($bound);
            $count = \count(explode('.', $bound));
            if ($low[0] > 0) {
                $high = [$low[0] + 1, 0, 0];
            } elseif (1 === $count) {
                $high = [1, 0, 0];
            } elseif ($low[1] > 0 || 2 === $count) {
                $high = [0, $low[1] + 1, 0];
            } else {
                $high = [0, 0, $low[2] + 1];
            }

            return self::compare($low, $installed) <= 0 && self::compare($installed, $high) < 0;
        }
        if (str_starts_with($part, '~')) {
            $bound = substr($part, 1);
            $low = self::tuple($bound);
            $high = \count(explode('.', $bound)) <= 2 ? [$low[0] + 1, 0, 0] : [$low[0], $low[1] + 1, 0];

            return self::compare($low, $installed) <= 0 && self::compare($installed, $high) < 0;
        }
        if (str_starts_with($part, '>=')) {
            return self::compare($installed, self::tuple(substr($part, 2))) >= 0;
        }
        if (str_starts_with($part, '<=')) {
            return self::compare($installed, self::tuple(substr($part, 2))) <= 0;
        }
        if (str_starts_with($part, '<')) {
            return self::compare($installed, self::tuple(substr($part, 1))) < 0;
        }
        if (str_starts_with($part, '>')) {
            return self::compare($installed, self::tuple(substr($part, 1))) > 0;
        }
        if (1 === preg_match('/^v?\d+(\.\d+)*\.[*x]$/', $part)) {
            $prefix = explode('.', ltrim($part, 'v'));
            array_pop($prefix);
            $low = self::tuple(implode('.', $prefix));
            $high = 1 === \count($prefix) ? [$low[0] + 1, 0, 0] : [$low[0], $low[1] + 1, 0];

            return self::compare($low, $installed) <= 0 && self::compare($installed, $high) < 0;
        }
        if (1 === preg_match('/^=?v?\d/', $part)) {
            return 0 === self::compare($installed, self::tuple(ltrim($part, '=')));
        }

        return true;
    }

    /**
     * @return array{int, int, int}
     */
    private static function tuple(string $version): array
    {
        $version = explode('+', explode('-', ltrim($version, 'v'))[0])[0];
        $parts = array_map(static fn (string $part): int => ctype_digit($part) ? (int) $part : 0, \array_slice(explode('.', $version), 0, 3));

        return [$parts[0], $parts[1] ?? 0, $parts[2] ?? 0];
    }

    /**
     * @param array{int, int, int} $a
     * @param array{int, int, int} $b
     */
    private static function compare(array $a, array $b): int
    {
        return $a <=> $b;
    }
}
