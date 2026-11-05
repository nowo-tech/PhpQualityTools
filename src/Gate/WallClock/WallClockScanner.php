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

    private const FUNCTIONS = ['time', 'date', 'gmdate', 'idate', 'date_create', 'date_create_immutable', 'strtotime'];

    private const RENDER_LIMIT = 120;

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

        // One linear pass: the matching closer and the first top-level comma of every bracket, so checking a call
        // never rescans its arguments (deeply nested calls stay linear).
        $close = [];
        $comma = [];
        $stack = [];
        foreach ($tokens as $index => $token) {
            $text = \is_array($token) ? $token[1] : $token;
            if ('(' === $text || '[' === $text || '{' === $text || '${' === $text) {
                $stack[] = $index;
            } elseif (')' === $text || ']' === $text || '}' === $text) {
                $open = array_pop($stack);
                if (null !== $open) {
                    $close[$open] = $index;
                }
            } elseif (',' === $text && [] !== $stack) {
                $comma[$stack[\count($stack) - 1]] ??= $index;
            }
        }

        $findings = [];
        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $finding = $this->phpFinding($tokens, $close, $comma, $i);
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
     * @param array<int, int>                               $close  index of `(` => index of its `)`
     * @param array<int, int>                               $comma  index of `(` => index of its first top-level `,`
     *
     * @return array{int, string}|null
     */
    private function phpFinding(array $tokens, array $close, array $comma, int $i): ?array
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
            $args = $this->arguments($tokens, $close, $comma, $i + 2);

            return $this->dateArguments($args, true)
                ? [$line, \sprintf('new %s(%s) reads the wall clock (%s)', ltrim($class[1], '\\'), $args['text'] ?? '', self::PHP_HINT)]
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

        // Only the watched functions; the name is checked before anything else is looked at.
        if (!\in_array($name, self::FUNCTIONS, true)) {
            return null;
        }

        // A global function call: not a method, a static call, a declaration or a namespaced function.
        if (\T_NAME_QUALIFIED === $token[0] || (\T_NAME_FULLY_QUALIFIED === $token[0] && 1 !== substr_count($token[1], '\\')) || \in_array($previousId, [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION, \T_NEW, \T_CONST], true)) {
            return null;
        }
        $args = $this->arguments($tokens, $close, $comma, $i + 1);
        if (null === $args) {
            return null;
        }
        $wallClock = match ($name) {
            'time' => 0 === $args['count'],
            'date', 'gmdate', 'idate' => 1 === $args['count'],
            'date_create', 'date_create_immutable' => $this->dateArguments($args, false),
            default => 1 === $args['count'] && $this->dateArguments($args, false), // strtotime
        };

        return $wallClock ? [$line, \sprintf('%s(%s) reads the wall clock (%s)', $name, $args['text'], self::PHP_HINT)] : null;
    }

    /**
     * Whether the date arguments mean "now": none (or no parentheses with `new`), or one relative literal first.
     *
     * @param array{count: int, first: array{0: int, 1: string, 2: int}|string|null, text: string}|null $args
     */
    private function dateArguments(?array $args, bool $allowNoParentheses): bool
    {
        if (null === $args) {
            return $allowNoParentheses;
        }
        if (0 === $args['count']) {
            return true;
        }
        $first = $args['first'];
        if (!\is_array($first) || \T_CONSTANT_ENCAPSED_STRING !== $first[0]) {
            return false;
        }

        return self::isRelativeDate(substr($first[1], 1, -1));
    }

    /**
     * The call whose `(` is at `$open`: argument count (0, 1, or 2 for "two or more"), the first argument when it is
     * a single token, and its rendered text; null when there is no `(`. Constant time apart from the bounded render.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<int, int>                               $close
     * @param array<int, int>                               $comma
     *
     * @return array{count: int, first: array{0: int, 1: string, 2: int}|string|null, text: string}|null
     */
    private function arguments(array $tokens, array $close, array $comma, int $open): ?array
    {
        if ('(' !== ($tokens[$open] ?? null)) {
            return null;
        }
        $end = $close[$open] ?? \count($tokens);
        if ($end === $open + 1) {
            return ['count' => 0, 'first' => null, 'text' => ''];
        }
        $firstEnd = $comma[$open] ?? $end;

        return [
            'count' => isset($comma[$open]) ? 2 : 1,
            'first' => $firstEnd === $open + 2 ? $tokens[$open + 1] : null,
            'text' => $this->render($tokens, $open + 1, $end),
        ];
    }

    /**
     * The source of tokens `$from` .. `$to - 1` (whitespace was dropped: one space between two words and after a
     * comma), cut at RENDER_LIMIT characters.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function render(array $tokens, int $from, int $to): string
    {
        $text = '';
        for ($i = $from; $i < $to; ++$i) {
            if (\strlen($text) > self::RENDER_LIMIT) {
                return substr($text, 0, self::RENDER_LIMIT) . '...';
            }
            $token = \is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            $space = str_ends_with($text, ',') || (1 === preg_match('/\w$/', $text) && 1 === preg_match('/^[\w$\\\\]/', $token));
            $text .= ($space ? ' ' : '') . $token;
        }

        return \strlen($text) > self::RENDER_LIMIT ? substr($text, 0, self::RENDER_LIMIT) . '...' : $text;
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
