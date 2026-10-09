<?php

namespace PSFS\apitests\support;

use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
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
    private static ?string $resolvedDatabaseName = null;
    private static bool $ownsDatabase = false;
    private static bool $seedIntegrityChecked = false;

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
        self::dropOwnedDatabase();
        self::resetRuntimeState();
    }

    public static function modulePath(): string
    {
        return CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE;
    }

    public static function dispatch(string $method, string $uri, array $headers = []): string
    {
        self::resetRuntimeState();
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($uri, PHP_URL_QUERY) ?: '';
        $queryParams = [];
        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
        }
        self::guardFastProfilePagination($queryParams, $uri);
        $_SERVER = array_merge([
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $path,
            'QUERY_STRING' => $queryString,
            'PATH_INFO' => $path,
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 8080,
            'HTTP_HOST' => 'localhost:8080',
        ], $headers);
        $_GET = $queryParams;
        $_POST = [];
        $_REQUEST = $queryParams;
        $_FILES = [];
        $_COOKIE = [];

        self::loadModulePropelConfig();
        Request::getInstance()->init();
        Api::setTest(true);
        Security::setTest(true);
        ResponseHelper::setTest(true);
        Config::setTest(true);
        $dispatcher = Dispatcher::getInstance();
        $result = (string)$dispatcher->run($path);
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
        self::assertSeedIntegrity();
        $pdo = self::connectTestDatabase();
        foreach (self::seedFiles() as $seedFile) {
            $sql = file_get_contents($seedFile);
            if (!is_string($sql) || trim($sql) === '') {
                throw new \RuntimeException('Seed SQL fixture is empty: ' . $seedFile);
            }
            self::executeSqlBatch($pdo, $sql);
        }
    }

    public static function tableExists(string $table): bool
    {
        $statement = self::connectTestDatabase()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);
        return (int)$statement->fetchColumn() > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        $statement = self::connectTestDatabase()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int)$statement->fetchColumn() > 0;
    }

    public static function columnLength(string $table, string $column): ?int
    {
        $statement = self::connectTestDatabase()->prepare(
            'SELECT character_maximum_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column'
        );
        $statement->execute(['table' => $table, 'column' => $column]);
        $length = $statement->fetchColumn();
        return false === $length || null === $length ? null : (int)$length;
    }

    public static function indexExists(string $table, string $index): bool
    {
        $statement = self::connectTestDatabase()->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
        );
        $statement->execute(['table' => $table, 'index' => $index]);
        return (int)$statement->fetchColumn() > 0;
    }

    public static function appliedMigrationCount(): int
    {
        if (!self::tableExists('phinxlog_client')) {
            return 0;
        }

        return (int)self::connectTestDatabase()->query('SELECT COUNT(*) FROM `phinxlog_client`')->fetchColumn();
    }

    /** @return list<int> */
    public static function appliedMigrationVersions(): array
    {
        if (!self::tableExists('phinxlog_client')) {
            return [];
        }
        $versions = self::connectTestDatabase()->query('SELECT version FROM `phinxlog_client` ORDER BY version')->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $versions);
    }

    /** @return list<int> */
    public static function migrationVersions(): array
    {
        $files = glob(self::modulePath() . '/Config/Migrations/*_AutoCLIENTSchemaDiff*.php') ?: [];
        sort($files, SORT_STRING);
        return array_map(static function (string $file): int {
            if (!preg_match('/^(\d+)_AutoCLIENTSchemaDiff\d+\.php$/', basename($file), $matches)) {
                throw new \RuntimeException('Unexpected generated Phinx migration name: ' . basename($file));
            }
            return (int)$matches[1];
        }, $files);
    }

    public static function countRows(string $table, string $column, string $value): int
    {
        foreach ([$table, $column] as $identifier) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                throw new \InvalidArgumentException('Invalid test database identifier: ' . $identifier);
            }
        }
        $statement = self::connectTestDatabase()->prepare(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $table, $column)
        );
        $statement->execute(['value' => $value]);
        return (int)$statement->fetchColumn();
    }

    private static function configureRuntime(): void
    {
        $config = self::$configBackup ?? [];
        $host = self::$resolvedHost ?: self::envValue(['API_DB_HOST', 'DB_HOST'], 'db');
        $port = self::envValue(['API_DB_PORT', 'DB_PORT'], '3306');
        $dbName = self::testDatabaseName();
        $dbUser = self::envValue(['API_DB_USER', 'DB_USER'], 'root');
        $dbPassword = self::envValue(['API_DB_PASSWORD', 'DB_PASSWORD'], 'psfs');

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
        $schemaStages = BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'SchemaStages';
        $schemaVersions = [
            $schemaStages . DIRECTORY_SEPARATOR . 'schema-v1.xml',
            $schemaStages . DIRECTORY_SEPARATOR . 'schema-v2.xml',
            BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'schema.xml',
        ];
        if (self::tableExists('CLIENT_TEST') || self::tableExists('phinxlog_client')) {
            throw new \RuntimeException('Phinx round-trip fixture must start from an empty database');
        }

        foreach ($schemaVersions as $index => $schemaFile) {
            $moduleSchema = self::modulePath() . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'schema.xml';
            if ($schemaFile !== $moduleSchema && !copy($schemaFile, $moduleSchema)) {
                throw new \RuntimeException('Unable to load migration schema stage ' . ($index + 1));
            }
            $generator->createStructureModule(self::MODULE, skipMigration: false);
            self::runMigrations();
        }
    }

    public static function runMigrations(?int $targetVersion = null): void
    {
        $target = null === $targetVersion ? '' : ' --target=' . $targetVersion;
        self::runConsoleCommand('psfs:migrate --module=' . self::MODULE . $target, 'Migration');
    }

    public static function rollbackMigrations(?int $targetVersion = null): void
    {
        $target = null === $targetVersion ? '' : ' --target=' . $targetVersion;
        self::runConsoleCommand('psfs:migrate:rollback --module=' . self::MODULE . $target, 'Rollback');
    }

    public static function runSeeders(): void
    {
        self::runConsoleCommand('psfs:seed --module=' . self::MODULE, 'Seeder');
    }

    private static function runConsoleCommand(string $arguments, string $operation): void
    {
        self::resetRuntimeState();
        $command = 'php src/bin/psfs ' . $arguments;
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
            throw new \RuntimeException($operation . ' command failed: ' . trim($stdout . PHP_EOL . $stderr));
        }
    }

    private static function prepareDatabase(): void
    {
        $host = self::$resolvedHost ?: self::envValue(['API_DB_HOST', 'DB_HOST'], 'db');
        $port = self::envValue(['API_DB_PORT', 'DB_PORT'], '3306');
        // API_DB_NAME belongs to PHPUnit's bootstrap. Only the explicit test override
        // may bypass an isolated database so API tests never mutate core_test.
        $configuredDbName = getenv('PSFS_API_TEST_DB_NAME');
        if (false === $configuredDbName || '' === $configuredDbName) {
            self::$resolvedDatabaseName = 'psfs_api_test_' . getmypid() . '_' . bin2hex(random_bytes(5));
            self::$ownsDatabase = true;
        } else {
            self::$resolvedDatabaseName = $configuredDbName;
            self::$ownsDatabase = false;
        }
        $dbName = self::testDatabaseName();
        $dbUser = self::envValue(['API_DB_USER', 'DB_USER'], 'root');
        $dbPassword = self::envValue(['API_DB_PASSWORD', 'DB_PASSWORD'], 'psfs');
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
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new \RuntimeException('Invalid API test database name');
        }
        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName));
    }

    private static function connectTestDatabase(): \PDO
    {
        $host = self::$resolvedHost ?: self::envValue(['API_DB_HOST', 'DB_HOST'], 'db');
        $port = self::envValue(['API_DB_PORT', 'DB_PORT'], '3306');
        $dbName = self::testDatabaseName();
        $dbUser = self::envValue(['API_DB_USER', 'DB_USER'], 'root');
        $dbPassword = self::envValue(['API_DB_PASSWORD', 'DB_PASSWORD'], 'psfs');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        return new \PDO($dsn, $dbUser, $dbPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function testDatabaseName(): string
    {
        return self::$resolvedDatabaseName ?? self::envValue(['PSFS_API_TEST_DB_NAME'], 'core_test');
    }

    private static function dropOwnedDatabase(): void
    {
        if (!self::$ownsDatabase || null === self::$resolvedDatabaseName) {
            self::$resolvedDatabaseName = null;
            self::$ownsDatabase = false;
            return;
        }

        $dbName = self::$resolvedDatabaseName;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new \RuntimeException('Refusing to remove invalid API test database name');
        }
        $host = self::$resolvedHost ?: self::envValue(['API_DB_HOST', 'DB_HOST'], 'db');
        $port = self::envValue(['API_DB_PORT', 'DB_PORT'], '3306');
        $dbUser = self::envValue(['API_DB_USER', 'DB_USER'], 'root');
        $dbPassword = self::envValue(['API_DB_PASSWORD', 'DB_PASSWORD'], 'psfs');
        $pdo = new \PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port), $dbUser, $dbPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $dbName));
        self::$resolvedDatabaseName = null;
        self::$ownsDatabase = false;
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
        SingletonRegistry::clear();
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
        ];
        return array_values(array_unique(array_filter($hosts)));
    }

    private static function envValue(array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false) {
                return (string)$value;
            }
        }
        return $default;
    }

    /**
     * @return array<int, string>
     */
    private static function seedFiles(): array
    {
        $seedDir = BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'client';
        if (!is_dir($seedDir)) {
            return [BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'client_seed.sql'];
        }
        $files = glob($seedDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    private static function executeSqlBatch(\PDO $pdo, string $sql): void
    {
        $statements = array_filter(array_map(static fn(string $statement): string => trim($statement), explode(';', $sql)));
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Avoid unbounded pagination in fast/CI profile.
     *
     * @param array<string, mixed> $queryParams
     */
    private static function guardFastProfilePagination(array $queryParams, string $uri): void
    {
        if (!self::isFastProfile()) {
            return;
        }
        $limit = isset($queryParams['__limit']) ? (int)$queryParams['__limit'] : null;
        if ($limit === -1) {
            throw new \RuntimeException(
                'Unbounded __limit=-1 is disabled in fast profile. URI: ' . $uri . '. Use nightly profile or set PSFS_ALLOW_UNBOUNDED_LIST=1'
            );
        }
    }

    private static function isFastProfile(): bool
    {
        if (getenv('PSFS_ALLOW_UNBOUNDED_LIST') === '1') {
            return false;
        }
        $profile = strtolower((string)(getenv('PSFS_TEST_PROFILE') ?: ''));
        if (in_array($profile, ['ci', 'fast'], true)) {
            return true;
        }
        return strtolower((string)(getenv('CI') ?: '')) === 'true';
    }

    private static function assertSeedIntegrity(): void
    {
        if (self::$seedIntegrityChecked) {
            return;
        }
        $seedDir = BASE_DIR . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'client';
        $manifestFile = $seedDir . DIRECTORY_SEPARATOR . 'checksums.sha256';
        if (!is_file($manifestFile)) {
            self::$seedIntegrityChecked = true;
            return;
        }
        $lines = file($manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $parts = preg_split('/\s+/', $trimmed, 2);
            if (!is_array($parts) || count($parts) !== 2) {
                throw new \RuntimeException('Invalid seed checksum manifest line: ' . $line);
            }
            $expectedHash = strtolower(trim($parts[0]));
            $relativePath = ltrim(trim($parts[1]), '*');
            $filePath = $seedDir . DIRECTORY_SEPARATOR . $relativePath;
            if (!is_file($filePath)) {
                throw new \RuntimeException('Missing seed file from checksum manifest: ' . $filePath);
            }
            $actualHash = strtolower((string)hash_file('sha256', $filePath));
            if ($actualHash !== $expectedHash) {
                throw new \RuntimeException('Seed checksum mismatch for ' . $filePath);
            }
        }
        self::$seedIntegrityChecked = true;
    }
}
