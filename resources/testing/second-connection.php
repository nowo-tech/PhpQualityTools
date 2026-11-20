<?php

declare(strict_types=1);

/*
 * Second database session of the real-database race and load tests (Testing\Concurrency\SecondConnection): it reads one
 * command per line from STDIN and answers one line per command on STDOUT, so a test can make it hold locks, wait and
 * commit while the test's own connection runs.
 *
 *   SQL <statement>   execute the statement; answers "OK" or "ERR <driver code> <SQLSTATE>"
 *   VAL <query>       run the query; answers "VAL <first column of the first row>" or "ERR …"
 *   SLEEP <ms>        wait, then answer "OK" (queue it before a COMMIT to release a lock while the test is blocked)
 *   EXIT              close the connection and stop
 *
 * Usage: php second-connection.php <vendor/autoload.php> <database url (mysql://…, pgsql://… or sqlite:///:memory:)>
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;

$autoload = $argv[1] ?? '';
$url = $argv[2] ?? '';
if ('' === $autoload || '' === $url || !is_file($autoload)) {
    fwrite(\STDERR, "Usage: php second-connection.php <vendor/autoload.php> <database url>\n");
    exit(2);
}

require $autoload;

$parser = new DsnParser(['mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite']);
$connection = DriverManager::getConnection($parser->parse($url));
// Bounded waits: a blocked statement answers within seconds (SQLite has no such setting).
if (str_starts_with($url, 'mysql')) {
    $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 15');
} elseif (!str_starts_with($url, 'sqlite')) {
    $connection->executeStatement("SET lock_timeout = '15s'");
}

fwrite(\STDOUT, "READY\n");

while (false !== ($line = fgets(\STDIN))) {
    $line = rtrim($line, "\r\n");
    if ('EXIT' === $line) {
        break;
    }

    if (str_starts_with($line, 'SLEEP ')) {
        usleep(1000 * (int) substr($line, 6));
        fwrite(\STDOUT, "OK\n");
        continue;
    }

    try {
        if (str_starts_with($line, 'SQL ')) {
            $sql = substr($line, 4);
            if (0 === stripos($sql, 'SELECT')) {
                // A query's rows are read, so the session can take the next statement.
                $connection->executeQuery($sql)->fetchAllNumeric();
            } else {
                $connection->executeStatement($sql);
            }
            fwrite(\STDOUT, "OK\n");
        } elseif (str_starts_with($line, 'VAL ')) {
            fwrite(\STDOUT, 'VAL ' . var_export($connection->fetchOne(substr($line, 4)), true) . "\n");
        } else {
            fwrite(\STDOUT, "ERR 0 unknown-command\n");
        }
    } catch (DriverException $exception) {
        fwrite(\STDOUT, \sprintf("ERR %d %s\n", $exception->getCode(), $exception->getSQLState() ?? '-'));
    }
}

$connection->close();
