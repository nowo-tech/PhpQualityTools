<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\WallClock;

/**
 * Finds reads of the real clock in test code: the `[line, message]` pairs of one PHP or JS / TS source.
 *
 * PHP (token based, so strings, comments, methods and other classes' `time()` never match):
 * - `new DateTime()` / `new DateTimeImmutable()` / `new Carbon()` / `date_create()` without an argument or with a
 *   relative date (`'now'`, `'today'`, `'tomorrow'`, `'yesterday'`, `'midnight'`, `'noon'`, `'next …'`, `'last …'`,
 *   `'+1 day'`, `'-2 weeks'`, `'… ago'`, weekday names) that carries no absolute date or timestamp;
 * - `strtotime('<relative date>')` without a base timestamp;
 * - `time()`, and `date()` / `gmdate()` / `idate()` without a timestamp argument;
 * - `Carbon::now()` / `today()` / `tomorrow()` / `yesterday()` (also `CarbonImmutable`).
 *
 * JS / TS: `new Date()` without an argument (outside `//` comments).
 *
 * A file that freezes the clock (see {@see PHP_FROZEN_MARKERS} / {@see JS_FROZEN_MARKERS}, plus extra markers)
 * has no finding. `@wallclock-ignore` in a comment on the same line or on the line above skips one finding.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class WallClockScanner
{
    public const IGNORE_MARKER = '@wallclock-ignore';

    /** Symfony MockClock / ClockSensitiveTrait::mockTime(), PHPUnit bridge ClockMock (time-sensitive group), Carbon / Chronos test now. */
    public const PHP_FROZEN_MARKERS = '/\b(?:MockClock|ClockMock|ClockSensitiveTrait|FrozenClock|mockTime\s*\(|setTestNow(?:AndTimezone)?\s*\(|Clock::set\s*\()|time-sensitive/';

    /** Jest / Vitest / Sinon fake timers, mockdate, timekeeper, Playwright page.clock. */
    public const JS_FROZEN_MARKERS = '/\b(?:useFakeTimers\s*\(|setSystemTime\s*\(|mockdate|MockDate|timekeeper|fake-timers|clock\.(?:install|setFixedTime)\s*\()/';

    private const DATE_CLASSES = ['datetime', 'datetimeimmutable', 'carbon', 'carbonimmutable'];

    private const CARBON_CLASSES = ['carbon', 'carbonimmutable'];

    private const CARBON_NOW = ['now', 'today', 'tomorrow', 'yesterday'];

    private const RELATIVE = '/(?:^|[^a-z])(?:now|today|tomorrow|yesterday|midnight|noon|next|last|this|ago|monday|tuesday|wednesday|thursday|friday|saturday|sunday)(?:[^a-z]|$)|^\s*[+-]?\s*\d+\s*(?:sec|second|min|minute|hour|day|week|fortnight|month|year)s?\b/';

    /** A calendar date, a `@timestamp` or a year (1900-2099) makes a relative phrase absolute ('first monday of january 2025'). */
    private const ABSOLUTE = '/\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\/\d{1,2}\/\d{2,4}|@\d+|\b(?:19|20)\d{2}\b/';

    private const PHP_HINT = 'freeze the clock: MockClock / ClockSensitiveTrait::mockTime(), or use a fixed date; @wallclock-ignore to opt out';

    private const SKIPPED = [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT];

    /**
     * @param list<string> $extraMarkers additional PCRE patterns (with delimiters) that mark a frozen clock
     */
    public function __construct(
        private array $extraMarkers = [],
    ) {
    }

    /**
     * @return list<array{int, string}>
     */
    public function scanPhp(string $source): array
    {
        if ($this->frozen($source, self::PHP_FROZEN_MARKERS)) {
            return [];
        }

        $tokens = [];
        $ignored = [];
        $raw = null;
        foreach (token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true) && str_contains($token[1], self::IGNORE_MARKER)) {
                $ignored[$token[2]] = true;
                // A comment alone on its line(s) also covers the next line.
                if (null === $raw || (\is_array($raw) && str_contains($raw[1], "\n"))) {
                    $ignored[$token[2] + substr_count(rtrim($token[1]), "\n") + 1] = true;
                }
            }
            if (!\is_array($token) || !\in_array($token[0], self::SKIPPED, true)) {
                $tokens[] = $token;
            }
            $raw = $token;
        }

        $findings = [];
        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $finding = $this->phpFinding($tokens, $i);
            if (null !== $finding && !isset($ignored[$finding[0]])) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @return list<array{int, string}>
     */
    public function scanJs(string $source): array
    {
        if ($this->frozen($source, self::JS_FROZEN_MARKERS)) {
            return [];
        }

        $findings = [];
        $lines = explode("\n", $source);
        foreach ($lines as $index => $line) {
            if (1 !== preg_match('/\bnew\s+Date\s*\(\s*\)/', $line, $match, \PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $before = substr($line, 0, $match[0][1]);
            $trimmed = ltrim($line);
            if (str_contains($before, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $above = ltrim($lines[$index - 1] ?? '');
            if (str_contains($line, self::IGNORE_MARKER) || (str_contains($above, self::IGNORE_MARKER) && 1 === preg_match('#^(//|/\*|\*)#', $above))) {
                continue;
            }
            $findings[] = [$index + 1, 'new Date() reads the wall clock (use fake timers: vi.useFakeTimers() / vi.setSystemTime(), jest.useFakeTimers(), or a fixed date)'];
        }

        return $findings;
    }

    public static function isRelativeDate(string $value): bool
    {
        $value = strtolower(trim($value));

        return '' === $value || (1 === preg_match(self::RELATIVE, $value) && 1 !== preg_match(self::ABSOLUTE, $value));
    }

    private function frozen(string $source, string $markers): bool
    {
        foreach ([$markers, ...$this->extraMarkers] as $pattern) {
            if (1 === preg_match($pattern, $source)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens significant tokens
     *
     * @return array{int, string}|null
     */
    private function phpFinding(array $tokens, int $i): ?array
    {
        $token = $tokens[$i];
        if (!\is_array($token)) {
            return null;
        }
        $line = $token[2];
        $previous = $tokens[$i - 1] ?? null;
        $previousId = \is_array($previous) ? $previous[0] : $previous;

        if (\T_NEW === $token[0]) {
            $class = $tokens[$i + 1] ?? null;
            if (!\is_array($class) || !\in_array($this->shortName($class), self::DATE_CLASSES, true)) {
                return null;
            }
            $args = $this->arguments($tokens, $i + 2);

            return $this->dateArguments($args, true)
                ? [$line, \sprintf('new %s(%s) reads the wall clock (%s)', ltrim($class[1], '\\'), $this->render($args), self::PHP_HINT)]
                : null;
        }

        if (!\in_array($token[0], [\T_STRING, \T_NAME_FULLY_QUALIFIED, \T_NAME_QUALIFIED], true)) {
            return null;
        }
        $name = $this->shortName($token);

        // Carbon::now() and friends.
        if (\in_array($name, self::CARBON_CLASSES, true) && \T_DOUBLE_COLON === (\is_array($tokens[$i + 1] ?? null) ? $tokens[$i + 1][0] : null)) {
            $method = $tokens[$i + 2] ?? null;
            if (\is_array($method) && \in_array(strtolower($method[1]), self::CARBON_NOW, true) && '(' === ($tokens[$i + 3] ?? null)) {
                return [$line, \sprintf('%s::%s() reads the wall clock (%s)', ltrim($token[1], '\\'), $method[1], self::PHP_HINT)];
            }

            return null;
        }

        // A global function call: not a method, a static call, a declaration or a namespaced function.
        if (\T_NAME_QUALIFIED === $token[0] || \in_array($previousId, [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION, \T_NEW, \T_CONST], true)) {
            return null;
        }
        $args = $this->arguments($tokens, $i + 1);
        if (null === $args) {
            return null;
        }
        $wallClock = match ($name) {
            'time' => [] === $args,
            'date', 'gmdate', 'idate' => 1 === \count($args),
            'date_create', 'date_create_immutable' => $this->dateArguments($args, false),
            'strtotime' => 1 === \count($args) && $this->dateArguments($args, false),
            default => false,
        };

        return $wallClock ? [$line, \sprintf('%s(%s) reads the wall clock (%s)', $name, $this->render($args), self::PHP_HINT)] : null;
    }

    /**
     * Whether the (first) date argument means "now": none (or no parentheses with `new`), or a relative literal.
     *
     * @param list<list<array{0: int, 1: string, 2: int}|string>>|null $args
     */
    private function dateArguments(?array $args, bool $allowNoParentheses): bool
    {
        if (null === $args) {
            return $allowNoParentheses;
        }
        if ([] === $args) {
            return true;
        }
        $first = $args[0];
        if (1 !== \count($first) || !\is_array($first[0]) || \T_CONSTANT_ENCAPSED_STRING !== $first[0][0]) {
            return false;
        }

        return self::isRelativeDate(substr($first[0][1], 1, -1));
    }

    /**
     * The arguments of the call whose `(` is at `$open`, split on top-level commas; null when there is no `(`.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return list<list<array{0: int, 1: string, 2: int}|string>>|null
     */
    private function arguments(array $tokens, int $open): ?array
    {
        if ('(' !== ($tokens[$open] ?? null)) {
            return null;
        }
        $args = [];
        $current = [];
        $depth = 0;
        $count = \count($tokens);
        for ($i = $open + 1; $i < $count; ++$i) {
            $token = $tokens[$i];
            $text = \is_array($token) ? $token[1] : $token;
            if (0 === $depth && (')' === $text || ',' === $text)) {
                if ([] !== $current) {
                    $args[] = $current;
                }
                if (')' === $text) {
                    break;
                }
                $current = [];
                continue;
            }
            if (\in_array($text, ['(', '[', '{'], true) || (\is_array($token) && \in_array($token[0], [\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                ++$depth;
            } elseif (\in_array($text, [')', ']', '}'], true)) {
                --$depth;
            }
            $current[] = $token;
        }

        return $args;
    }

    /**
     * @param list<list<array{0: int, 1: string, 2: int}|string>>|null $args
     */
    private function render(?array $args): string
    {
        $parts = [];
        foreach ($args ?? [] as $arg) {
            $text = '';
            foreach ($arg as $token) {
                $token = \is_array($token) ? $token[1] : $token;
                // Whitespace tokens were dropped: put one space back between two words (new DateTimeZone).
                $text .= (1 === preg_match('/\w$/', $text) && 1 === preg_match('/^[\w$\\\\]/', $token) ? ' ' : '') . $token;
            }
            $parts[] = $text;
        }

        return implode(', ', $parts);
    }

    /**
     * @param array{0: int, 1: string, 2: int} $token
     */
    private function shortName(array $token): string
    {
        $parts = explode('\\', strtolower($token[1]));

        return end($parts);
    }
}
