<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Concurrency;

use Composer\Autoload\ClassLoader;

/**
 * A second database session in its own PHP process (`resources/testing/second-connection.php`), so a test can make it
 * hold locks and block while the test's own connection runs (two-connection race and deadlock tests).
 *
 * ```php
 * $other = new SecondConnection($url);            // mysql://user:pass@host/db or pgsql://…
 * $other->execute('START TRANSACTION');
 * $other->execute('UPDATE account SET balance = 0 WHERE id = 1');   // "OK" or "ERR <code> <SQLSTATE>"
 * $other->send('UPDATE account SET balance = 1 WHERE id = 1');      // blocks on the lock: read it later
 * // … the test's own connection commits …
 * self::assertSame('OK', $other->await());
 * $other->close();
 * ```
 *
 * Needs `doctrine/dbal` ^4.0 and a MySQL / MariaDB or PostgreSQL server (`pdo_mysql` / `pdo_pgsql`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class SecondConnection
{
    /** A blocked statement answers within the lock wait timeout of the server (the helper sets 15 s). */
    private const READ_TIMEOUT_SECONDS = 90;

    /** @var resource */
    private $process;

    /** @var array<int, resource> */
    private array $pipes;

    /**
     * @param string      $url      Database URL of the second session
     * @param string|null $autoload The Composer autoloader the helper process loads (default: the running one)
     */
    public function __construct(string $url, ?string $autoload = null)
    {
        $autoload ??= \dirname((string) (new \ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $script = \dirname(__DIR__, 3) . '/resources/testing/second-connection.php';

        // Test-only helper process; every read below is bounded by stream_set_timeout().
        // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout
        $process = proc_open([\PHP_BINARY, $script, $autoload, $url], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
        \is_resource($process) || self::fail('Could not start the second database session.');
        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_timeout($this->pipes[1], self::READ_TIMEOUT_SECONDS);
        'READY' === $this->readLine() || self::fail('The second database session did not start.');
    }

    /**
     * Runs a statement and waits for its answer ("OK" or "ERR <code> <SQLSTATE>").
     *
     * @phpstan-impure
     */
    public function execute(string $sql): string
    {
        $this->send($sql);

        return $this->await();
    }

    /**
     * Sends a statement without waiting (it may block on a lock, or wait its turn after a sleep); read its answer
     * later with {@see self::await()}.
     */
    public function send(string $sql): void
    {
        fwrite($this->pipes[0], 'SQL ' . $sql . "\n");
    }

    /**
     * Waits `$milliseconds` in the second session ("OK"); queue it before a COMMIT to release a lock while the test
     * is blocked.
     */
    public function sleep(int $milliseconds): void
    {
        fwrite($this->pipes[0], 'SLEEP ' . $milliseconds . "\n");
    }

    /**
     * Runs a query and returns its first value as var_export() wrote it ("VAL '3'"), or "ERR …".
     *
     * @phpstan-impure
     */
    public function value(string $sql): string
    {
        fwrite($this->pipes[0], 'VAL ' . $sql . "\n");

        return $this->await();
    }

    /** @phpstan-impure */
    public function await(): string
    {
        return $this->readLine();
    }

    public function close(): void
    {
        if (!isset($this->pipes[0])) {
            return;
        }
        fwrite($this->pipes[0], "EXIT\n");
        proc_close($this->process);
        $this->pipes = [];
    }

    private static function fail(string $message): never
    {
        throw new \RuntimeException($message);
    }

    private function readLine(): string
    {
        $line = fgets($this->pipes[1]);
        if (false === $line) {
            self::fail('The second database session ended unexpectedly.');
        }

        return rtrim($line, "\r\n");
    }
}
