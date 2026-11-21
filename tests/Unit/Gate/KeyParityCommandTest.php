<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Translation\KeyParityCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyParityCommand::class)]
final class KeyParityCommandTest extends TestCase
{
    use TempTreeTrait;

    private const DIR = 'src/Resources/translations';

    private const CATALOGUE = "# comment\n\nmenu:\n    home: Home\n    'about': \"About\"\ntitle: Title\n";

    /**
     * @param list<string>               $locales
     * @param array<string, string|null> $extra
     *
     * @return array<string, string|null>
     */
    private function catalogues(string $domain, array $locales = ['en', 'es'], string $content = self::CATALOGUE, array $extra = []): array
    {
        $files = [];
        foreach ($locales as $locale) {
            $files[self::DIR . "/{$domain}.{$locale}.yaml"] = $content;
        }

        return $files + $extra;
    }

    public function testMatchingCataloguesAndTwigKeysPass(): void
    {
        $root = $this->makeTree($this->catalogues('NowoFooBundle', extra: [
            'src/Resources/views/a.html.twig' => "{% trans_default_domain 'NowoFooBundle' %}\n{{ 'menu.home'|trans }} {{ 'title'|trans({'%n%': 1}) }} {{ 'other.domain'|trans({}, 'messages') }}",
            'src/Resources/views/b.html.twig' => "{{ 'menu.about'|trans({}, 'NowoFooBundle') }} {{ 'title'|trans }} {{ name|trans }}",
        ]));

        [$code, $out, $err] = $this->runCommand($root, ['--locales=en,es']);

        self::assertSame(0, $code, $err);
        self::assertSame("Translation key parity OK: NowoFooBundle (2 locales, 4 keys, 3 used in Twig).\n", $out);
    }

    public function testEveryDomainWithAReferenceFileIsCheckedByDefault(): void
    {
        $root = $this->makeTree(
            $this->catalogues('NowoAaaBundle', ['en', 'es', 'it', 'fr', 'pt', 'de', 'nl'])
            + $this->catalogues('NowoZzzSeed', ['en', 'es', 'it', 'fr', 'pt', 'de', 'nl']),
        );

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame(
            "Translation key parity OK: NowoAaaBundle (7 locales, 4 keys, 0 used in Twig).\n"
            . "Translation key parity OK: NowoZzzSeed (7 locales, 4 keys, 0 used in Twig).\n",
            $out,
        );
    }

    public function testMissingAndExtraKeysAreReportedPerLocale(): void
    {
        $root = $this->makeTree([
            self::DIR . '/NowoFooBundle.en.yaml' => self::CATALOGUE,
            self::DIR . '/NowoFooBundle.es.yaml' => "menu:\n    home: Inicio\n    extra: Extra\ntitle: Titulo\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--locales=en,es']);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame("ERROR: key mismatch in NowoFooBundle.es.yaml vs en\n  missing: menu.about\n  extra: menu.extra\n", $err);
    }

    public function testMissingLocaleFilesAreReported(): void
    {
        $root = $this->makeTree($this->catalogues('NowoFooBundle', ['en']));

        [$code, , $err] = $this->runCommand($root, ['--locales=en,es,fr']);

        self::assertSame(1, $code);
        self::assertSame("ERROR: missing locale files:\n" . $root . '/' . self::DIR . '/NowoFooBundle.es.yaml' . "\n" . $root . '/' . self::DIR . '/NowoFooBundle.fr.yaml' . "\n", $err);
    }

    public function testATwigKeyMissingFromTheReferenceCatalogueIsReported(): void
    {
        $root = $this->makeTree($this->catalogues('NowoFooBundle', extra: [
            'src/Resources/views/a.html.twig' => "{% trans_default_domain 'NowoFooBundle' %}{{ 'menu.home'|trans }} {{ 'menu.nope'|trans }} {{ 'zzz'|trans }}",
        ]));

        [$code, , $err] = $this->runCommand($root, ['--locales=en,es']);

        self::assertSame(1, $code);
        self::assertSame("ERROR: Twig keys missing from the en NowoFooBundle catalogue:\n  missing: menu.nope\n  missing: zzz\n", $err);

        [$code, $out] = $this->runCommand($root, ['--locales=en,es', '--no-twig']);
        self::assertSame(0, $code);
        self::assertStringContainsString('0 used in Twig', $out);

        [$code, $out] = $this->runCommand($root, ['--locales=en,es', '--views=missing']);
        self::assertSame(0, $code);
        self::assertStringContainsString('0 used in Twig', $out);
    }

    public function testCallsWithUnresolvableOrUnbalancedArgumentsAreHandled(): void
    {
        $views = [
            // a variable domain cannot be resolved: skipped; an escaped quote and nested brackets stay inside one argument
            'src/Resources/views/a.html.twig' => "{{ 'unknown.a'|trans({}, domain) }} {{ 'title'|trans({'a': [1, 2], 'b': 'x\\'y,z'}, 'NowoFooBundle') }}",
            // an unbalanced call ends the file: no arguments are read
            'src/Resources/views/b.html.twig' => "{% trans_default_domain 'NowoFooBundle' %}{{ 'title'|trans({'a': 1",
        ];
        $root = $this->makeTree($this->catalogues('NowoFooBundle', extra: $views));

        [$code, $out, $err] = $this->runCommand($root, ['--locales=en,es']);

        self::assertSame(0, $code, $err);
        self::assertStringContainsString('1 used in Twig', $out);
    }

    public function testAnotherReferenceLocaleDirectoriesAndDomains(): void
    {
        $root = $this->makeTree([
            'translations/App.fr.yaml' => "a: 1\n: orphan\n",
            'translations/App.de.yaml' => 'a: 2',
            'translations/Other.fr.yaml' => 'b: 1',
            'translations/Other.de.yaml' => 'c: 1',
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--dir=./translations/', '--locales=de', '--reference=fr', '--domain=App']);
        self::assertSame(0, $code, $err);
        self::assertSame("Translation key parity OK: App (2 locales, 1 keys, 0 used in Twig).\n", $out);

        [$code, , $err] = $this->runCommand($root, ['--dir=translations', '--locales=de', '--reference=fr']);
        self::assertSame(1, $code);
        self::assertStringContainsString('key mismatch in Other.de.yaml vs fr', $err);
    }

    public function testWithoutTranslationsOrCataloguesTheCheckIsSkipped(): void
    {
        $empty = $this->makeTree(['README.md' => '']);
        [$code, $out] = $this->runCommand($empty, []);
        self::assertSame(0, $code);
        self::assertSame('No translations yet (' . self::DIR . "); key parity check skipped.\n", $out);

        $noCatalogues = $this->makeTree([self::DIR . '/notes.txt' => 'x']);
        [$code, $out] = $this->runCommand($noCatalogues, []);
        self::assertSame(0, $code);
        self::assertSame('No catalogues in ' . self::DIR . "; key parity check skipped.\n", $out);
    }

    public function testAnExplicitMissingDirectoryOrDomainIsAnError(): void
    {
        $root = $this->makeTree(['README.md' => '']);

        [$code, $out, $err] = $this->runCommand($root, ['--dir=translations']);
        self::assertSame(2, $code);
        self::assertSame('', $out);
        self::assertSame("ERROR: translations directory not found: translations (under {$root})\n", $err);

        [$code, , $err] = $this->runCommand($root, ['--domain=NowoFooBundle']);
        self::assertSame(2, $code);
        self::assertStringContainsString('translations directory not found: ' . self::DIR, $err);

        [$code] = $this->runCommand($root, []);
        self::assertSame(0, $code, 'without options a missing directory is still a skip');
    }

    public function testCataloguesWithoutTheReferenceLocaleFail(): void
    {
        $root = $this->makeTree([self::DIR . '/NowoFooBundle.es.yaml' => 'a: 1', self::DIR . '/NowoFooBundle.fr.yml' => 'a: 1']);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertStringContainsString('has catalogues but none for the reference locale en', $err);

        [$code] = $this->runCommand($root, ['--domain=NowoFooBundle', '--locales=es,fr']);
        self::assertSame(1, $code, 'a named domain without its reference file is still a missing file');
    }

    public function testYmlCataloguesAreReadLikeYaml(): void
    {
        $root = $this->makeTree([
            self::DIR . '/NowoFooBundle.en.yml' => "menu:\n    home: Home\n",
            self::DIR . '/NowoFooBundle.es.yaml' => "menu:\n    home: Inicio\n",
            self::DIR . '/NowoBarBundle.en.yaml' => "a: 1\n",
            self::DIR . '/NowoBarBundle.es.yml' => "a: 2\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--locales=en,es']);

        self::assertSame(0, $code, $err);
        self::assertSame(
            "Translation key parity OK: NowoBarBundle (2 locales, 1 keys, 0 used in Twig).\n"
            . "Translation key parity OK: NowoFooBundle (2 locales, 2 keys, 0 used in Twig).\n",
            $out,
        );

        file_put_contents($root . '/' . self::DIR . '/NowoBarBundle.es.yml', "b: 2\n");
        [$code, , $err] = $this->runCommand($root, ['--locales=en,es']);
        self::assertSame(1, $code);
        self::assertStringContainsString('key mismatch in NowoBarBundle.es.yml vs en', $err);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree($this->catalogues('NowoFooBundle'));

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-translation-key-parity', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--domain', 'Unknown or empty option --domain'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
        }

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root), '--locales= en ,, es ', '--views=views', '--domain=NowoFooBundle']);
        self::assertSame(0, $code);
        self::assertStringContainsString('2 locales', $out);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function runCommand(string $cwd, array $args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new KeyParityCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
