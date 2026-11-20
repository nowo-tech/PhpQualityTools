<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing\Concurrency;

use NowoTech\PhpQualityTools\Testing\Concurrency\SecondConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The protocol of the second session, against an in-memory SQLite database (no server needed; MySQL and PostgreSQL
 * behave the same through the same helper).
 */
#[CoversClass(SecondConnection::class)]
final class SecondConnectionTest extends TestCase
{
    private const URL = 'sqlite:///:memory:';

    public function testRunsStatementsAndQueriesAndReportsTheirErrors(): void
    {
        $session = new SecondConnection(self::URL);

        try {
            self::assertSame('OK', $session->execute('CREATE TABLE account (id INTEGER PRIMARY KEY, balance INTEGER NOT NULL)'));
            self::assertSame('OK', $session->execute('INSERT INTO account (id, balance) VALUES (1, 30), (2, 12)'));
            self::assertSame('OK', $session->execute('SELECT * FROM account'));
            self::assertSame('VAL 30', $session->value('SELECT balance FROM account WHERE id = 1'));
            self::assertMatchesRegularExpression('/^ERR \d+ \S+$/', $session->execute('INSERT INTO account (id, balance) VALUES (1, 1)'));
            self::assertMatchesRegularExpression('/^ERR \d+ \S+$/', $session->value('SELECT nothing FROM nowhere'));
        } finally {
            $session->close();
        }
    }

    public function testASendCanBeAnsweredLaterAndASleepAnswersAfterTheWait(): void
    {
        $session = new SecondConnection(self::URL);

        try {
            $session->send('CREATE TABLE t (id INTEGER)');
            $session->sleep(30);
            $started = microtime(true);
            self::assertSame('OK', $session->await());
            self::assertSame('OK', $session->await());
            self::assertGreaterThanOrEqual(0.025, microtime(true) - $started);
        } finally {
            $session->close();
        }
    }

    public function testAnUnknownCommandIsAnsweredWithAnError(): void
    {
        $session = new SecondConnection(self::URL);

        try {
            $method = new \ReflectionMethod($session, 'readLine');
            $pipes = new \ReflectionProperty($session, 'pipes');
            fwrite($pipes->getValue($session)[0], "NOPE\n");

            self::assertSame('ERR 0 unknown-command', $method->invoke($session));
        } finally {
            $session->close();
        }
    }

    public function testCloseIsIdempotent(): void
    {
        $session = new SecondConnection(self::URL);

        $session->close();
        $session->close();

        $this->addToAssertionCount(1);
    }

    public function testAHelperThatCannotStartIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The second database session ended unexpectedly.');

        new SecondConnection(self::URL, __DIR__ . '/missing-autoload.php');
    }
}
