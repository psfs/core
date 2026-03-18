<?php

$host = getenv('API_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('API_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
$dbName = getenv('API_DB_NAME') ?: getenv('DB_NAME') ?: 'core_test';
$user = getenv('API_DB_USER') ?: getenv('DB_USER') ?: 'root';
$password = getenv('API_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';

$lastException = null;
for ($i = 0; $i < 60; $i++) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName));
        echo "MySQL ready and database prepared" . PHP_EOL;
        exit(0);
    } catch (Throwable $exception) {
        $lastException = $exception;
        usleep(1_000_000);
    }
}

fwrite(STDERR, 'MySQL setup failed: ' . ($lastException ? $lastException->getMessage() : 'unknown error') . PHP_EOL);
exit(1);

