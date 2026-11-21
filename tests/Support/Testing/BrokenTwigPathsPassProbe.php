<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Testing;

use NowoTech\PhpQualityTools\Testing\TwigPathsPassTestCase;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Twig\GoodTwigPathsPass;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Twig\WrongOrderTwigPathsPass;

/**
 * Not run by PHPUnit: TwigPathsPassTestCaseTest calls its test methods directly and expects them to fail.
 */
final class BrokenTwigPathsPassProbe extends TwigPathsPassTestCase
{
    protected function passClass(): string
    {
        return WrongOrderTwigPathsPass::class;
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
