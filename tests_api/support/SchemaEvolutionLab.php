<?php

namespace PSFS\apitests\support;

use PSFS\base\config\Config;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\SingletonRegistry;
use PSFS\base\types\helpers\GeneratorHelper;
use PSFS\services\GeneratorService;

/**
 * Takes a module through successive schema.xml versions against a real MySQL database, the way a developer does:
 * generate the module (which writes a Phinx migration from the difference), run the migrations, look at the result.
 * Everything is removed at the end: the module, the database and the configuration keys.
 */
final class SchemaEvolutionLab
{
    public const MODULE = 'MIGLAB';
    private const IGNORED_TABLES = ['phinxlog_miglab', 'propel_migration'];

    private ?array $configBackup = null;
    private string $database = '';
    private ?\PDO $pdo = null;
    private bool $autoloaded = false;
    private bool $removeTables = false;

    /**
     * @param bool $removeTables migrations.remove_tables: drop the tables schema.xml no longer has
     */
    public function start(bool $removeTables = false): void
    {
        $this->removeTables = $removeTables;
        $this->configBackup = Config::getInstance()->dumpConfig();
        $this->database = 'psfs_evolution_' . getmypid() . '_' . bin2hex(random_bytes(4));
        $this->server()->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $this->database));
        $this->configureRuntime();
        GeneratorHelper::deleteDir($this->modulePath());
        GeneratorService::getInstance()->createStructureModule(self::MODULE, true, skipMigration: true);
    }

    public function stop(): void
    {
        if (is_array($this->configBackup)) {
            Config::save($this->configBackup, []);
            Config::getInstance()->loadConfigData(true);
        }
        GeneratorHelper::deleteDir($this->modulePath());
        if ('' !== $this->database) {
            $this->server()->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->database));
        }
        $this->pdo = null;
        $this->resetRuntimeState();
    }

    public function modulePath(): string
    {
        return CORE_DIR . DIRECTORY_SEPARATOR . self::MODULE;
    }

    /**
     * Makes schema.xml the given version and regenerates the module, which writes the migration when the
     * database differs from it
     *
     * @return string[] the migration files this call generated
     */
    public function useSchema(string $schemaFile): array
    {
        $before = $this->migrationFiles();
        copy($schemaFile, $this->modulePath() . '/Config/schema.xml');
        if (!$this->autoloaded) {
            require_once $this->modulePath() . '/autoload.php';
            $this->autoloaded = true;
        }
        GeneratorService::getInstance()->createStructureModule(self::MODULE, skipMigration: false);
        return array_values(array_diff($this->migrationFiles(), $before));
    }

    /**
     * @return string[] migration files of the module, oldest first
     */
    public function migrationFiles(): array
    {
        $files = glob($this->modulePath() . '/Config/Migrations/*.php') ?: [];
        sort($files);
        return $files;
    }

    /**
     * @return int[] versions of the migration files, oldest first
     */
    public function versions(): array
    {
        return array_map(static fn(string $file): int => (int)basename($file), $this->migrationFiles());
    }

    public function migrate(?int $target = null): void
    {
        $this->console('psfs:migrate' . (null === $target ? '' : ' --target=' . $target));
    }

    public function rollback(int $target): void
    {
        $this->console('psfs:migrate:rollback --target=' . $target);
    }

    public function execute(string $sql): void
    {
        $this->database()->exec($sql);
    }

    public function scalar(string $sql): mixed
    {
        return $this->database()->query($sql)->fetchColumn();
    }

    /**
     * The structure of the database as MySQL reports it: columns, indexes and foreign keys, without ordering
     *
     * @return array{columns: array<string, array<string, array<string, mixed>>>, indexes: array<string, array<string, array<string, mixed>>>, foreignKeys: array<string, array<string, array<string, mixed>>>}
     */
    public function snapshot(?string $database = null): array
    {
        $database ??= $this->database;
        return [
            'columns' => $this->columns($database),
            'indexes' => $this->indexes($database),
            'foreignKeys' => $this->foreignKeys($database),
        ];
    }

    /**
     * What Propel itself would create for the current schema.xml: the SQL it generates, run on an empty database.
     * It is the reference the migrated database is compared with, and it does not depend on the migrations.
     *
     * @return array{columns: array, indexes: array, foreignKeys: array}
     */
    public function propelReference(): array
    {
        $reference = $this->database . '_reference';
        $server = $this->server();
        $server->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $reference));
        $server->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $reference));
        try {
            $sql = (string)file_get_contents($this->modulePath() . '/Config/Sql/' . self::MODULE . '.sql');
            $connection = new \PDO(
                sprintf('mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4', $this->host(), $reference),
                'root',
                'psfs',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $connection->exec($sql);
            return $this->snapshot($reference);
        } finally {
            $server->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $reference));
        }
    }

    private function columns(string $database): array
    {
        $rows = $this->query($database,
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?'
        );
        $columns = [];
        foreach ($rows as $row) {
            $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = [
                'type' => $row['COLUMN_TYPE'],
                'nullable' => 'YES' === $row['IS_NULLABLE'],
                'default' => $row['COLUMN_DEFAULT'],
                'autoIncrement' => str_contains((string)$row['EXTRA'], 'auto_increment'),
            ];
        }
        return $this->sorted($columns);
    }

    private function indexes(string $database): array
    {
        $rows = $this->query($database,
            'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS_LIST, GROUP_CONCAT(IFNULL(SUB_PART, 0) ORDER BY SEQ_IN_INDEX) AS PARTS'
            . ' FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE'
        );
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[$row['TABLE_NAME']][$row['INDEX_NAME']] = [
                'unique' => '0' === (string)$row['NON_UNIQUE'],
                'columns' => $row['COLUMNS_LIST'],
                'parts' => $row['PARTS'],
            ];
        }
        return $this->sorted($indexes);
    }

    private function foreignKeys(string $database): array
    {
        $rows = $this->query($database,
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS LOCAL_COLUMNS,'
            . ' k.REFERENCED_TABLE_NAME, GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS FOREIGN_COLUMNS,'
            . ' r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k'
            . ' JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . ' WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL'
            . ' GROUP BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, r.DELETE_RULE, r.UPDATE_RULE'
        );
        $foreignKeys = [];
        foreach ($rows as $row) {
            $foreignKeys[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']] = [
                'columns' => $row['LOCAL_COLUMNS'],
                'references' => $row['REFERENCED_TABLE_NAME'] . '(' . $row['FOREIGN_COLUMNS'] . ')',
                'onDelete' => $row['DELETE_RULE'],
                'onUpdate' => $row['UPDATE_RULE'],
            ];
        }
        return $this->sorted($foreignKeys);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function query(string $database, string $sql): array
    {
        $statement = $this->database()->prepare($sql);
        $statement->execute([$database]);
        return array_filter(
            $statement->fetchAll(\PDO::FETCH_ASSOC),
            static fn(array $row): bool => !in_array(strtolower((string)$row['TABLE_NAME']), self::IGNORED_TABLES, true)
        );
    }

    private function sorted(array $tree): array
    {
        ksort($tree);
        foreach ($tree as &$children) {
            ksort($children);
        }
        return $tree;
    }

    private function configureRuntime(): void
    {
        $config = $this->configBackup ?? [];
        $lower = strtolower(self::MODULE);
        foreach (['host' => $this->host(), 'port' => '3306', 'name' => $this->database, 'user' => 'root', 'password' => 'psfs'] as $key => $value) {
            $config['db.' . $key] = $value;
            $config[$lower . '.db.' . $key] = $value;
        }
        $config['migrations.remove_tables'] = $this->removeTables;
        $config['debug'] = true;
        $config['skip.route_generation'] = false;
        Config::save($config, []);
        Config::getInstance()->loadConfigData(true);
    }

    private function console(string $arguments): void
    {
        $this->resetRuntimeState();
        $command = 'php src/bin/psfs ' . $arguments . ' --module=' . self::MODULE;
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_DIR);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to run ' . $command);
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (0 !== proc_close($process)) {
            throw new \RuntimeException($command . ' failed: ' . trim($output));
        }
    }

    private function resetRuntimeState(): void
    {
        SingletonRegistry::clear();
        Router::dropInstance();
        Request::dropInstance();
        Security::dropInstance();
    }

    private function host(): string
    {
        return getenv('API_DB_HOST') ?: 'db';
    }

    private function server(): \PDO
    {
        return new \PDO(sprintf('mysql:host=%s;port=3306;charset=utf8mb4', $this->host()), 'root', 'psfs', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    private function database(): \PDO
    {
        return $this->pdo ??= new \PDO(
            sprintf('mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4', $this->host(), $this->database),
            'root',
            'psfs',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
