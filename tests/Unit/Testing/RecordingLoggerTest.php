<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(RecordingLogger::class)]
final class RecordingLoggerTest extends TestCase
{
    public function testKeepsEveryRecordInOrder(): void
    {
        $logger = new RecordingLogger();
        $logger->warning('first {id}', ['id' => 7]);
        $logger->error(new class implements \Stringable {
            public function __toString(): string
            {
                return 'second';
            }
        });

        self::assertSame(
            [
                ['level' => LogLevel::WARNING, 'message' => 'first {id}', 'context' => ['id' => 7]],
                ['level' => LogLevel::ERROR, 'message' => 'second', 'context' => []],
            ],
            $logger->records,
        );
        self::assertSame(['first {id}', 'second'], $logger->messages());
        self::assertSame(['warning', 'error'], $logger->levels());
        self::assertSame(['warning: first {id}', 'error: second'], $logger->lines());
    }

    public function testAnUnusualLevelIsNamedByItsTypeOrItsString(): void
    {
        $logger = new RecordingLogger();
        $logger->log(new class implements \Stringable {
            public function __toString(): string
            {
                return 'custom';
            }
        }, 'stringable level');
        $logger->log(42, 'int level');

        self::assertSame(['custom', 'int'], $logger->levels());
    }

    public function testFiltersByLevelAndFragment(): void
    {
        $logger = new RecordingLogger();
        $logger->info('sent mail');
        $logger->error('mail failed');
        $logger->info('done');

        self::assertSame(['sent mail', 'done'], array_column($logger->recordsWithLevel(LogLevel::INFO), 'message'));
        self::assertSame([], $logger->recordsWithLevel(LogLevel::DEBUG));
        self::assertTrue($logger->has('failed'));
        self::assertFalse($logger->has('secret'));
        self::assertFalse((new RecordingLogger())->has('anything'));
    }

    public function testContextValuesFlattenScalarsStringablesNullAndObjects(): void
    {
        $logger = new RecordingLogger();
        $logger->info('ctx', [
            'user' => 'alice',
            'nested' => ['age' => 30, 'ok' => true, 'none' => null],
            'enum' => LogLevel::class,
            'object' => new \stdClass(),
            'stringable' => new class implements \Stringable {
                public function __toString(): string
                {
                    return 'text';
                }
            },
        ]);

        self::assertSame(['alice', '30', '1', '', LogLevel::class, 'stdClass', 'text'], $logger->contextValues());
    }

    public function testDumpSerialisesEverythingAndSurvivesUnencodableValues(): void
    {
        $logger = new RecordingLogger();
        $logger->warning('token leaked', ['token' => 's3cret']);
        $logger->warning('bad bytes', ['raw' => "\xB1\x31"]);

        $dump = $logger->dump();
        self::assertStringContainsString('s3cret', $dump);
        self::assertStringContainsString('bad bytes', $dump);
        self::assertJson($dump);
        self::assertStringNotContainsString('s3cret', (new RecordingLogger())->dump());
    }

    public function testClearForgetsTheRecords(): void
    {
        $logger = new RecordingLogger();
        $logger->info('x');
        $logger->clear();

        self::assertSame([], $logger->records);
    }
}
