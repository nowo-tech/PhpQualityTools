<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration\Testing;

use NowoTech\PhpQualityTools\Testing\MissingTranslationsTrait;
use NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel\ProfilerTestKernel;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * MissingTranslationsTrait against a real kernel, translator and profiler.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class MissingTranslationsTraitTest extends WebTestCase
{
    use MissingTranslationsTrait;

    protected static function getKernelClass(): string
    {
        return ProfilerTestKernel::class;
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(ProfilerTestKernel::varDir());
        parent::tearDownAfterClass();
    }

    public function testCollectsUniqueMissingMessages(): void
    {
        $client = self::createClient();

        self::assertSame([
            ['locale' => 'en', 'domain' => 'messages', 'id' => 'missing.title'],
            ['locale' => 'en', 'domain' => 'admin', 'id' => 'Save'],
            ['locale' => 'es', 'domain' => 'admin', 'id' => 'legacy.one'],
        ], $this->collectMissingTranslations($client, '/translated'));
        self::assertStringContainsString('Hello', $client->getCrawler()->filterXPath('//body')->text());
    }

    public function testFailsListingEveryMissingMessage(): void
    {
        $client = self::createClient();

        try {
            $this->assertNoMissingTranslations($client, '/translated');
            self::fail('Expected missing translations.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("GET /translated uses 3 missing translation(s) (Profiler → Translation → Missing messages):\n  en messages: missing.title\n  en admin: Save\n  es admin: legacy.one", $e->getMessage());
        }
    }

    public function testAllowlistByFullEntryDomainIdOrPrefix(): void
    {
        $client = self::createClient();

        $ignored = $this->assertNoMissingTranslations($client, '/translated', ['en messages: missing.title', 'admin: Save', 'legacy.*']);

        self::assertSame(['en messages: missing.title', 'en admin: Save', 'es admin: legacy.one'], $ignored);
    }

    public function testAllowlistById(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('uses 1 missing translation(s)');
        $this->assertNoMissingTranslations($client, '/translated', ['missing.title', 'es admin: *']);
    }

    public function testRequestMustSucceed(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('GET /missing returned HTTP 404.');
        $this->collectMissingTranslations($client, '/missing');
    }

    public function testProfilerMustBeEnabled(): void
    {
        $client = self::createClient(['environment' => 'noprofiler']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No profile for GET /translated');
        $this->collectMissingTranslations($client, '/translated');
    }

    public function testTranslationCollectorNeedsTheTranslator(): void
    {
        $client = self::createClient(['environment' => 'nodb']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The profile has no "translation" collector');
        $this->collectMissingTranslations($client, '/translated');
    }
}
