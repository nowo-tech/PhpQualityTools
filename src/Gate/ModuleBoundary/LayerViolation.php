<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * One layer-rule finding: `file:line` references a name (or calls a method) a layer rule forbids.
 *
 * The baseline key (`rule|file|subject`) has no line number, so a baseline survives edits above the finding.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class LayerViolation
{
    /**
     * @param string|null $layer   Layer of the file (null = outside every layer of its context)
     * @param string      $subject Fully-qualified name, or `->method()` for a call
     */
    public function __construct(
        public string $context,
        public ?string $layer,
        public string $mode,
        public string $rule,
        public string $file,
        public int $line,
        public string $subject,
        public string $message = '',
    ) {
    }

    public function key(): string
    {
        return $this->rule . '|' . $this->file . '|' . $this->subject;
    }

    public function describe(): string
    {
        $verb = str_starts_with($this->subject, '->') ? 'calls' : 'references';

        return \sprintf(
            '%s:%d %s %s (%s %s, layer rule %s)%s',
            $this->file,
            $this->line,
            $verb,
            $this->subject,
            $this->context,
            $this->layer ?? '(no layer)',
            $this->rule,
            '' !== $this->message ? ': ' . $this->message : '.',
        );
    }
}
