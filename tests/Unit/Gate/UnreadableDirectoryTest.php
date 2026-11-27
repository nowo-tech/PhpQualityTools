<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Filesystem\UnreadableDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnreadableDirectory::class)]
final class UnreadableDirectoryTest extends TestCase
{
    use TempTreeTrait;

    public function testTheFirstUnreadableDirectoryIsAppendedWhenTheMessageLacksIt(): void
    {
        $root = $this->makeTree(['a/ok.txt' => 'x', 'b/locked/c/x.txt' => 'x']);
        $locked = $root . '/b/locked';
        chmod($locked, 0o000);

        try {
            if (is_readable($locked)) {
                self::markTestSkipped('Permissions are not enforced (running as root).');
            }
            $bare = UnreadableDirectory::message(new \UnexpectedValueException('Failed to open directory: Permission denied'), $root . '/missing', $root);
            $withPath = UnreadableDirectory::message(new \UnexpectedValueException("open({$locked}): Permission denied"), $root);
        } finally {
            chmod($locked, 0o755);
        }

        self::assertSame('Failed to open directory: Permission denied (' . $locked . ')', $bare);
        self::assertSame("open({$locked}): Permission denied", $withPath);
    }

    public function testNothingIsAppendedWhenEveryDirectoryIsReadable(): void
    {
        $root = $this->makeTree(['a/ok.txt' => 'x']);

        self::assertSame('boom', UnreadableDirectory::message(new \UnexpectedValueException('boom'), $root, $root . '/a/ok.txt'));
    }
}
