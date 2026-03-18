<?php

namespace PSFS\apitests\support;

use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\config\Config;
use PSFS\base\types\Api;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\base\types\helpers\ResponseHelper;
use PSFS\Dispatcher;
use PSFS\services\GeneratorService;

final class ClientModuleHarness
{
    private const MODULE = 'CLIENT';
    private const MODULE_LOWER = 'client';
    private static int $refs = 0;
    private static ?array $configBackup = null;
    private static ?string $moduleBackupPath = null;
    private static ?string $resolvedHost = null;

    public static function acquire(): void
    {
        self::$refs++;
        if (self::$refs > 1) {
            self::loadModulePropelConfig();
            self::resetSeedData();
            return;
        }
        self::$configBackup = Config::getInstance()->dumpConfig();
        self::backupExistingModule();
        self::prepareDatabase();
        self::configureRuntime();
        self::generateModuleStructure();
        self::loadModulePropelConfig();
        self::generateMigrations();
        self::runMigrations();
        self::resetSeedData();
    }

    public static function release(): void
    {
        self::$refs--;
        if (self::$refs > 0) {
            return;
        }
        self::restoreConfig();
        self::cleanupModule();
        self::restoreModuleBackup();
        self::resetRuntimeState();
    }

    public static function modulePath(): string
    {
        return CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE;
    }

    public static function dispatch(string $method, string $uri, array $headers = []): string
    {
        self::resetRuntimeState();
        $_SERVER = array_merge([
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ], $headers);
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_FILES = [];
        $_COOKIE = [];

        self::loadModulePropelConfig();
        Request::getInstance()->init();
        Api::setTest(true);
        Security::setTest(true);
        ResponseHelper::setTest(true);
        Config::setTest(true);
        $dispatcher = Dispatcher::getInstance();
        $result = (string)$dispatcher->run($uri);
        Api::setTest(false);
        Config::setTest(false);
        ResponseHelper::setTest(false);
        Security::setTest(false);
        @restore_error_handler();
        @restore_exception_handler();
        return $result;
    }

    public static function resetSeedData(): void
    {
        $sql = file_get_contents(BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'client_seed.sql');
        if (!is_string($sql) || $sql === '') {
            throw new \RuntimeException('Seed SQL fixture is empty');
        }
        $pdo = self::connectTestDatabase();
        $pdo->exec($sql);
    }

    private static function configureRuntime(): void
    {
        $config = self::$configBackup ?? [];
        $host = self::$resolvedHost ?: (getenv('API_DB_HOST') ?: getenv('DB_HOST') ?: 'db');
        $port = getenv('API_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
        $dbName = getenv('API_DB_NAME') ?: getenv('DB_NAME') ?: 'core_test';
        $dbUser = getenv('API_DB_USER') ?: getenv('DB_USER') ?: 'root';
        $dbPassword = getenv('API_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'psfs';

        $config['debug'] = true;
        $config['home.action'] = $config['home.action'] ?? 'admin';
        $config['default.language'] = $config['default.language'] ?? 'en_US';
        $config['skip.route_generation'] = false;
        $config['db.host'] = $host;
        $config['db.port'] = $port;
        $config['db.name'] = $dbName;
        $config['db.user'] = $dbUser;
        $config['db.password'] = $dbPassword;
        $config[self::MODULE_LOWER . '.db.host'] = $host;
        $config[self::MODULE_LOWER . '.db.port'] = $port;
        $config[self::MODULE_LOWER . '.db.name'] = $dbName;
        $config[self::MODULE_LOWER . '.db.user'] = $dbUser;
        $config[self::MODULE_LOWER . '.db.password'] = $dbPassword;
        $config['api.secret'] = '';
        $config[self::MODULE_LOWER . '.api.secret'] = '';
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
    }

    private static function generateModuleStructure(): void
    {
        $generator = GeneratorService::getInstance();
        $generator->createStructureModule(self::MODULE, true, skipMigration: true);
        $fixtureConfig = BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'Config';
        GeneratorHelper::copyr($fixtureConfig, self::modulePath() . DIRECTORY_SEPARATOR . 'Config');
        require_once self::modulePath() . DIRECTORY_SEPARATOR . 'autoload.php';
        $generator->createStructureModule(self::MODULE, skipMigration: true);
    }

    private static function loadModulePropelConfig(): void
    {
        $moduleConfig = self::modulePath() . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($moduleConfig)) {
            require $moduleConfig;
        }
    }

    private static function generateMigrations(): void
    {
        $generator = GeneratorService::getInstance();
        $generator->createStructureModule(self::MODULE, skipMigration: false);
    }

    private static function runMigrations(): void
    {
        self::resetRuntimeState();
        $command = 'php src/bin/psfs psfs:migrate --module=' . self::MODULE;
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($command, $descriptor, $pipes, BASE_DIR);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Unable to execute migration command');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Migration command failed: ' . trim($stdout . PHP_EOL . $stderr));
        }
    }

    private static function prepareDatabase(): void
    {
        $host = self::$resolvedHost ?: (getenv('API_DB_HOST') ?: getenv('DB_HOST') ?: 'db');
        $port = getenv('API_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
        $dbName = getenv('API_DB_NAME') ?: getenv('DB_NAME') ?: 'core_test';
        $dbUser = getenv('API_DB_USER') ?: getenv('DB_USER') ?: 'root';
        $dbPassword = getenv('API_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'psfs';
        $hosts = self::candidateHosts($host);
        $pdo = null;
        $lastError = null;
        foreach ($hosts as $candidateHost) {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $candidateHost, $port);
            for ($attempt = 0; $attempt < 60; $attempt++) {
                try {
                    $pdo = new \PDO($dsn, $dbUser, $dbPassword, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                    self::$resolvedHost = $candidateHost;
                    break 2;
                } catch (\PDOException $exception) {
                    $lastError = $exception;
                    usleep(500000);
                }
            }
        }
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('Unable to connect to MySQL test service: ' . ($lastError?->getMessage() ?? 'unknown error'));
        }
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName));
    }

    private static function connectTestDatabase(): \PDO
    {
        $host = self::$resolvedHost ?: (getenv('API_DB_HOST') ?: getenv('DB_HOST') ?: 'db');
        $port = getenv('API_DB_PORT') ?: getenv('DB_PORT') ?: '3306';
        $dbName = getenv('API_DB_NAME') ?: getenv('DB_NAME') ?: 'core_test';
        $dbUser = getenv('API_DB_USER') ?: getenv('DB_USER') ?: 'root';
        $dbPassword = getenv('API_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'psfs';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        return new \PDO($dsn, $dbUser, $dbPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function backupExistingModule(): void
    {
        $modulePath = self::modulePath();
        if (is_dir($modulePath)) {
            self::$moduleBackupPath = CORE_DIR . DIRECTORY_SEPARATOR . '.CLIENT_BACKUP_' . date('YmdHis');
            rename($modulePath, self::$moduleBackupPath);
        }
    }

    private static function cleanupModule(): void
    {
        $modulePath = self::modulePath();
        if (is_dir($modulePath)) {
            self::deleteDir($modulePath);
        }
    }

    private static function restoreModuleBackup(): void
    {
        if (self::$moduleBackupPath !== null && is_dir(self::$moduleBackupPath)) {
            rename(self::$moduleBackupPath, self::modulePath());
            self::$moduleBackupPath = null;
        }
    }

    private static function restoreConfig(): void
    {
        if (is_array(self::$configBackup)) {
            Config::save(self::$configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
        self::$resolvedHost = null;
    }

    private static function deleteDir(string $path): void
    {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                self::deleteDir($itemPath);
                continue;
            }
            unlink($itemPath);
        }
        rmdir($path);
    }

    private static function resetRuntimeState(): void
    {
        if (method_exists(Dispatcher::class, 'dropInstance')) {
            Dispatcher::dropInstance();
        }
        Router::dropInstance();
        Request::dropInstance();
        Security::dropInstance();
    }

    /**
     * @param string $preferred
     * @return array
     */
    private static function candidateHosts(string $preferred): array
    {
        $hosts = [
            $preferred,
            'db',
            '127.0.0.1',
            'host.docker.internal',
        ];
        return array_values(array_unique(array_filter($hosts)));
    }
}
