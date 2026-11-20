<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Translation;

/**
 * `bin/check-translation-key-parity`: fail when the translation YAML files of a domain do not share the same nested
 * key set across the required locales (REQ-I18N-002), or when a Twig template translates a literal key of the domain
 * that the reference catalogue lacks.
 *
 * Files are `<dir>/<Domain>.<locale>.yaml` (or `.yml`). Keys are flattened from the indentation of the YAML lines (no YAML parser
 * needed); the Twig check reads `'key'|trans` / `'key'|trans({...}, 'Domain')` and `trans_default_domain`. A domain
 * argument that is not a string literal cannot be resolved statically and is skipped.
 *
 * Exit codes: 0 OK, 1 mismatch, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class KeyParityCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-translation-key-parity [--root=<dir>] [--dir=<dir>] [--views=<dir>] [--domain=<name>]...
                                            [--locales=<en,es,...>] [--reference=<locale>] [--no-twig]

        Fails when the <Domain>.<locale>.yaml catalogues of a domain do not share the same nested keys, or when a Twig
        template translates a literal key of the domain that the reference catalogue lacks.
          --root=<dir>        bundle root; every other path is relative to it (default: current directory)
          --dir=<dir>         translations directory (default: src/Resources/translations)
          --views=<dir>       Twig directory for the usage check (default: src/Resources/views)
          --domain=<name>     domain to check, repeatable (default: every domain with a <Domain>.<reference>.yaml file)
          --locales=<list>    required locales, comma separated (default: en,es,it,fr,pt,de,nl)
          --reference=<code>  locale the others are compared with (default: en)
          --no-twig           skip the Twig usage check
        Without a translations directory, or with one without catalogues, the check is skipped (exit 0); a directory with
        catalogues but none for the reference locale fails (exit 1). .yaml and .yml files are read.
        Exit codes: 0 OK, 1 mismatch, 2 usage error.
        TXT;

    private const DEFAULT_LOCALES = ['en', 'es', 'it', 'fr', 'pt', 'de', 'nl'];

    /** A literal key piped to |trans, with or without arguments: 'key'|trans, 'key'|trans(...). */
    private const TRANS_CALL = '/([\'"])([A-Za-z0-9_.\-]+)\1\s*\|\s*trans\b/';

    private const DEFAULT_DOMAIN = '/\{%-?\s*trans_default_domain\s+([\'"])([^\'"]+)\1/';

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $root = $this->cwd;
        $dir = 'src/Resources/translations';
        $views = 'src/Resources/views';
        $domains = [];
        $locales = self::DEFAULT_LOCALES;
        $reference = 'en';
        $twig = true;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--no-twig' === $arg) {
                $twig = false;
            } elseif (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            } elseif ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--dir' === $name) {
                $dir = trim($value, '/');
            } elseif ('--views' === $name) {
                $views = trim($value, '/');
            } elseif ('--domain' === $name) {
                $domains[] = $value;
            } elseif ('--locales' === $name) {
                $locales = array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $locale): bool => '' !== $locale));
            } elseif ('--reference' === $name) {
                $reference = $value;
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        if (!\in_array($reference, $locales, true)) {
            $locales[] = $reference;
        }

        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }
        $translations = $root . '/' . $dir;
        if (!is_dir($translations)) {
            fwrite($this->stdout, "No translations yet ({$dir}); key parity check skipped.\n");

            return 0;
        }
        if ([] === $domains) {
            $catalogues = [...(glob($translations . '/*.yaml') ?: []), ...(glob($translations . '/*.yml') ?: [])];
            if ([] === $catalogues) {
                fwrite($this->stdout, "No catalogues in {$dir}; key parity check skipped.\n");

                return 0;
            }
            foreach ($catalogues as $file) {
                $name = (string) preg_replace('/\.ya?ml$/', '', basename($file));
                if (str_ends_with($name, '.' . $reference)) {
                    $domains[] = substr($name, 0, -\strlen('.' . $reference));
                }
            }
            $domains = array_values(array_unique($domains));
            sort($domains);
            if ([] === $domains) {
                fwrite($this->stderr, "ERROR: {$dir} has catalogues but none for the reference locale {$reference}: name the domains with --domain or fix the file names.\n");

                return 1;
            }
        }

        $status = 0;
        foreach ($domains as $domain) {
            $status |= $this->checkDomain($root, $translations, $twig ? $views : null, $domain, $locales, $reference);
        }

        return $status;
    }

    /**
     * @param list<string> $locales
     */
    private function checkDomain(string $root, string $translations, ?string $views, string $domain, array $locales, string $reference): int
    {
        $files = [];
        foreach ($locales as $locale) {
            $files[$locale] = "{$translations}/{$domain}.{$locale}." . (!is_file("{$translations}/{$domain}.{$locale}.yaml") && is_file("{$translations}/{$domain}.{$locale}.yml") ? 'yml' : 'yaml');
        }
        $missing = array_filter($files, static fn (string $file): bool => !is_file($file));
        if ([] !== $missing) {
            fwrite($this->stderr, "ERROR: missing locale files:\n" . implode("\n", $missing) . "\n");

            return 1;
        }

        $expected = $this->flatten($files[$reference]);
        $failed = false;
        foreach ($files as $path) {
            $keys = $this->flatten($path);
            $extra = array_values(array_diff($keys, $expected));
            $absent = array_values(array_diff($expected, $keys));
            sort($extra);
            sort($absent);
            if ([] !== $extra || [] !== $absent) {
                $failed = true;
                fwrite($this->stderr, 'ERROR: key mismatch in ' . basename($path) . " vs {$reference}\n");
                foreach ($absent as $key) {
                    fwrite($this->stderr, "  missing: {$key}\n");
                }
                foreach ($extra as $key) {
                    fwrite($this->stderr, "  extra: {$key}\n");
                }
            }
        }
        if ($failed) {
            return 1;
        }

        $used = [];
        if (null !== $views && is_dir($root . '/' . $views)) {
            foreach ($this->twigFiles($root . '/' . $views) as $twig) {
                $used = array_merge($used, $this->twigKeys((string) file_get_contents($twig), $domain));
            }
            $used = array_values(array_unique($used));
            $unknown = array_values(array_diff($used, $expected));
            sort($unknown);
            if ([] !== $unknown) {
                fwrite($this->stderr, "ERROR: Twig keys missing from the {$reference} {$domain} catalogue:\n");
                foreach ($unknown as $key) {
                    fwrite($this->stderr, "  missing: {$key}\n");
                }

                return 1;
            }
        }

        fwrite($this->stdout, \sprintf(
            "Translation key parity OK: %s (%d locales, %d keys, %d used in Twig).\n",
            $domain,
            \count($locales),
            \count($expected),
            \count($used),
        ));

        return 0;
    }

    /**
     * Every dotted key path of a YAML catalogue, from the indentation of its lines.
     *
     * @return list<string>
     */
    private function flatten(string $path): array
    {
        $keys = [];
        $stack = [];
        foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $raw) {
            if ('' === trim($raw) || str_starts_with(ltrim($raw), '#')) {
                continue;
            }
            $indent = \strlen($raw) - \strlen(ltrim($raw, ' '));
            $key = trim(trim(explode(':', $raw, 2)[0]), '"\'');
            if ('' === $key) {
                continue;
            }
            while ([] !== $stack && $stack[array_key_last($stack)][0] >= $indent) {
                array_pop($stack);
            }
            $stack[] = [$indent, $key];
            $keys[implode('.', array_column($stack, 1))] = true;
        }

        return array_map(strval(...), array_keys($keys));
    }

    /**
     * @return list<string>
     */
    private function twigFiles(string $dir): array
    {
        $files = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The literal keys translated in `$wanted`: the 2nd/3rd-argument form or `trans_default_domain`.
     *
     * @return list<string>
     */
    private function twigKeys(string $source, string $wanted): array
    {
        $fileDomain = 1 === preg_match(self::DEFAULT_DOMAIN, $source, $default) ? $default[2] : null;
        $keys = [];
        preg_match_all(self::TRANS_CALL, $source, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $rest = $match[0][1] + \strlen($match[0][0]);
            while ($rest < \strlen($source) && (' ' === $source[$rest] || "\t" === $source[$rest])) {
                ++$rest;
            }
            $domain = $fileDomain;
            if ($rest < \strlen($source) && '(' === $source[$rest]) {
                $args = $this->splitArgs($source, $rest) ?? [];
                if (\count($args) >= 2) {
                    // A non-literal domain (a variable) cannot be resolved statically: skip it.
                    $domain = 1 === preg_match('/^([\'"])([^\'"]+)\1$/', $args[1], $literal) ? $literal[2] : null;
                }
            }
            if ($domain === $wanted) {
                $keys[] = $match[2][0];
            }
        }

        return $keys;
    }

    /**
     * The top-level comma-separated arguments of the call that opens at `$source[$start] === '('`.
     *
     * @return list<string>|null
     */
    private function splitArgs(string $source, int $start): ?array
    {
        $depth = 0;
        $quote = '';
        $args = [];
        $current = '';
        for ($i = $start, $length = \strlen($source); $i < $length; ++$i) {
            $char = $source[$i];
            if ('' !== $quote) {
                $current .= $char;
                if ($char === $quote && '\\' !== $source[$i - 1]) {
                    $quote = '';
                }
                continue;
            }
            if ('\'' === $char || '"' === $char) {
                $quote = $char;
                $current .= $char;
            } elseif (str_contains('([{', $char)) {
                ++$depth;
                if ($depth > 1) {
                    $current .= $char;
                }
            } elseif (str_contains(')]}', $char)) {
                --$depth;
                if (0 === $depth) {
                    if ('' !== trim($current)) {
                        $args[] = trim($current);
                    }

                    return $args;
                }
                $current .= $char;
            } elseif (',' === $char && 1 === $depth) {
                $args[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        return null;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
