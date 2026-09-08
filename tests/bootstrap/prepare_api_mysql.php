<?php

$host = getenv('API_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('API_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
$dbName = getenv('API_DB_NAME') ?: getenv('DB_NAME') ?: 'core_test';
$user = getenv('API_DB_USER') ?: getenv('DB_USER') ?: 'root';
$password = getenv('API_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';
$timeoutSeconds = (float) (getenv('API_DB_TIMEOUT') ?: '90');
$timeoutSeconds = $timeoutSeconds > 0 ? $timeoutSeconds : 90.0;
$deadline = microtime(true) + $timeoutSeconds;

$lastException = null;
$attempt = 0;
do {
    $attempt++;
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName));
        echo "MySQL ready and database prepared" . PHP_EOL;
        exit(0);
    } catch (Throwable $exception) {
        $lastException = $exception;
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            break;
        }

        if ($attempt === 1 || $attempt % 10 === 0) {
            fwrite(STDERR, sprintf("Waiting for MySQL (attempt %d, %.0fs remaining)\n", $attempt, $remaining));
        }

        usleep((int) (min(1.0, $remaining) * 1_000_000));
    }
} while (microtime(true) < $deadline);

fwrite(STDERR, sprintf(
    "MySQL setup failed after %.1fs: %s\n",
    $timeoutSeconds,
    $lastException ? $lastException->getMessage() : 'unknown error'
));
exit(1);
