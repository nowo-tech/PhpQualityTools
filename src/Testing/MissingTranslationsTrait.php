<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Missing translations of a rendered page, read from the profiler's `translation` collector (functional tests).
 *
 * The same list as Profiler → Translation → "Missing messages": every `trans()` call of the request whose
 * message was found in no catalogue of the locale or its fallbacks (`DataCollectorTranslator::MESSAGE_MISSING`).
 *
 * ```php
 * final class PagesTranslatedTest extends WebTestCase
 * {
 *     use MissingTranslationsTrait;
 *
 *     public function testDashboardIsFullyTranslated(): void
 *     {
 *         $client = static::createClient();
 *         $this->assertNoMissingTranslations($client, '/es/dashboard', allowlist: ['validators: This value*']);
 *     }
 * }
 * ```
 *
 * Needs `phpunit/phpunit`, `symfony/framework-bundle` + `symfony/browser-kit` + `symfony/translation`, `framework.translator`
 * and the profiler enabled in the test environment (`framework.profiler`, `collect: false` is enough: the trait enables
 * collection for its own request). The page is available afterwards through `$client->getCrawler()`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
trait MissingTranslationsTrait
{
    /**
     * Requests `$url` with the profiler enabled, asserts a 2xx response and returns its missing messages.
     *
     * @return list<array{locale: string, domain: string, id: string}> Unique, in first-use order
     */
    protected function collectMissingTranslations(KernelBrowser $client, string $url, string $method = 'GET'): array
    {
        $client->enableProfiler();
        $client->request($method, $url);
        $status = $client->getResponse()->getStatusCode();
        Assert::assertTrue($status >= 200 && $status < 300, \sprintf('%s %s returned HTTP %d.', $method, $url, $status));

        $profile = $client->getProfile();
        Assert::assertInstanceOf(Profile::class, $profile, \sprintf('No profile for %s %s: enable framework.profiler in the test environment.', $method, $url));
        Assert::assertTrue($profile->hasCollector('translation'), 'The profile has no "translation" collector: enable framework.translator.');
        $collector = $profile->getCollector('translation');
        Assert::assertInstanceOf(TranslationDataCollector::class, $collector);

        $messages = $collector->getMessages();
        if ($messages instanceof Data) {
            $messages = $messages->getValue(true);
        }

        $missing = [];
        foreach ((array) $messages as $message) {
            if (!\is_array($message) || DataCollectorTranslator::MESSAGE_MISSING !== ($message['state'] ?? null)) {
                continue;
            }
            $entry = [
                'locale' => (string) ($message['locale'] ?? ''),
                'domain' => (string) ($message['domain'] ?? ''),
                'id' => (string) ($message['id'] ?? ''),
            ];
            $missing[self::formatMissingTranslation($entry)] = $entry;
        }

        return array_values($missing);
    }

    /**
     * Fails when `$url` uses a message missing from the catalogues, except the allowlisted ones.
     *
     * An allowlist entry matches `"locale domain: id"`, `"domain: id"` or `"id"`; a trailing `*` matches a prefix
     * (e.g. `"validators: *"` allows every missing validator message, `"es messages: legacy.*"` one key prefix in one locale).
     *
     * @param list<string> $allowlist
     *
     * @return list<string> The allowlisted missing messages that were ignored (`"locale domain: id"`)
     */
    protected function assertNoMissingTranslations(KernelBrowser $client, string $url, array $allowlist = [], string $method = 'GET'): array
    {
        $reported = [];
        $ignored = [];
        foreach ($this->collectMissingTranslations($client, $url, $method) as $entry) {
            $formatted = self::formatMissingTranslation($entry);
            if (self::isAllowlistedTranslation($entry, $allowlist)) {
                $ignored[] = $formatted;

                continue;
            }
            $reported[] = $formatted;
        }

        Assert::assertSame([], $reported, \sprintf(
            "%s %s uses %d missing translation(s) (Profiler → Translation → Missing messages):\n  %s",
            $method,
            $url,
            \count($reported),
            implode("\n  ", $reported),
        ));

        return $ignored;
    }

    /**
     * @param array{locale: string, domain: string, id: string} $entry
     */
    private static function formatMissingTranslation(array $entry): string
    {
        return $entry['locale'] . ' ' . $entry['domain'] . ': ' . $entry['id'];
    }

    /**
     * @param array{locale: string, domain: string, id: string} $entry
     * @param list<string>                                      $allowlist
     */
    private static function isAllowlistedTranslation(array $entry, array $allowlist): bool
    {
        $candidates = [self::formatMissingTranslation($entry), $entry['domain'] . ': ' . $entry['id'], $entry['id']];
        foreach ($allowlist as $pattern) {
            $prefix = str_ends_with($pattern, '*') ? substr($pattern, 0, -1) : null;
            foreach ($candidates as $candidate) {
                if (null === $prefix ? $candidate === $pattern : str_starts_with($candidate, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
