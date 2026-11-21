<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\TwigPathsPassTestCase;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Twig\GoodTwigPathsPass;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * How a bundle adopts TwigPathsPassTestCase.
 */
#[CoversClass(TwigPathsPassTestCase::class)]
final class GoodTwigPathsPassTest extends TwigPathsPassTestCase
{
    protected function parameters(): array
    {
        return ['nowo_fixture.web_ui.enabled' => true];
    }

    protected function passClass(): string
    {
        return GoodTwigPathsPass::class;
    }

    protected function twigNamespace(): string
    {
        return GoodTwigPathsPass::TWIG_NAMESPACE;
    }

    protected function viewsDirectory(): string
    {
        return GoodTwigPathsPass::views();
    }
}
