<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Script that runs inside the sandbox to prepare and measure the regeneration of a module with many tables.
 * `prepare` runs once; `measure` runs in a fresh process so the cost of loading the generated classes is included.
 */
final class RegenerationScripts
{
    public const REGENERATE = <<<'PHP'
<?php
define('PSFS_UNIT_TESTING_EXECUTION', true);
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/bootstrap.php';

[$script, $step, $state, $tables, $module, $database] = $argv + [null, 'measure', 'regen', 10, 'REGEN', ''];
$tables = (int)$tables;
$generator = PSFS\services\GeneratorService::getInstance();
$moduleDir = __DIR__ . '/modules/' . $module;

function tableXml(int $index): string {
    $name = sprintf('ITEM%03d', $index);
    $parent = $index > 1 ? sprintf("\n        <foreign-key name=\"fk_%s\" foreignTable=\"ITEM%03d\"><reference local=\"ID_PARENT\" foreign=\"ID\"/></foreign-key>", $name, $index - 1) : '';
    return <<<XML
    <table name="$name" namespace="$name">
        <column name="ID" type="INTEGER" primaryKey="true" autoIncrement="true"/>
        <column name="NUMBER" type="INTEGER" required="true"/>
        <column name="NAME" type="VARCHAR" size="100" required="true"/>
        <column name="SUMMARY" type="VARCHAR" size="200"/>
        <column name="KIND" type="ENUM" valueSet="DEV,TEST,QA,PROD" required="true"/>
        <column name="CHECKER" type="BOOLEAN"/>
        <column name="ID_PARENT" type="INTEGER"/>
        <behavior name="i18n"><parameter name="i18n_columns" value="NAME"/><parameter name="default_locale" value="es"/></behavior>
        <behavior name="timestampable"/>$parent
    </table>
XML;
}

function writeSchema(string $moduleDir, string $module, int $tables): void {
    $body = implode("\n", array_map('tableXml', range(1, $tables)));
    file_put_contents($moduleDir . '/Config/schema.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<database name="$module" defaultIdMethod="native" namespace="$module\\Models" tablePrefix="{$module}_">
    <vendor type="mysql"><parameter name="Engine" value="InnoDB"/><parameter name="Charset" value="utf8"/><parameter name="Collate" value="utf8_unicode_ci"/></vendor>
$body
</database>
XML);
}

function configureDatabase(string $module, string $database): void {
    $config = PSFS\base\config\Config::getInstance()->dumpConfig();
    $lower = strtolower($module);
    foreach (['host' => 'db', 'port' => '3306', 'name' => $database, 'user' => 'root', 'password' => 'psfs'] as $key => $value) {
        $config['db.' . $key] = $value;
        $config[$lower . '.db.' . $key] = $value;
    }
    PSFS\base\config\Config::save($config, []);
    PSFS\base\config\Config::getInstance()->loadConfigData(true);
}

function applyMigrations(string $module): void {
    // the module list is cached: refresh it so the command finds the module that was just generated
    exec('php -d xdebug.mode=off src/bin/psfs psfs:deploy:project 2>&1');
    exec('php -d xdebug.mode=off src/bin/psfs psfs:migrate --module=' . escapeshellarg($module) . ' 2>&1', $output, $code);
    if (0 !== $code) {
        throw new RuntimeException("migration failed:\n" . implode("\n", $output));
    }
}

/** @return array<string,string> checksum of every generated file except the migrations (their names carry a timestamp) */
function moduleFiles(string $moduleDir): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($moduleDir));
        if (!str_contains($relative, 'Migrations')) {
            $files[$relative] = sha1_file($file->getPathname());
        }
    }
    ksort($files);
    return $files;
}

if ('prepare' === $step) {
    $pdo = new PDO('mysql:host=db;port=3306;charset=utf8mb4', 'root', 'psfs', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4");
    configureDatabase($module, $database);
    $generator->createStructureModule($module, true, skipMigration: true);
    writeSchema($moduleDir, $module, $tables);
    require_once $moduleDir . '/autoload.php';
    $generator->createStructureModule($module, skipMigration: true);
    if ('regen' === $state) {
        $generator->createStructureModule($module, skipMigration: false);
        applyMigrations($module);
    }
    exit(0);
}

require_once $moduleDir . '/autoload.php';
memory_reset_peak_usage();
$began = microtime(true);
$generator->createStructureModule($module, skipMigration: false);
if (getenv('REGEN_TREE_FILE')) {
    file_put_contents(getenv('REGEN_TREE_FILE'), json_encode(moduleFiles($moduleDir), JSON_PRETTY_PRINT));
}
echo json_encode([
    'wall_ms' => (microtime(true) - $began) * 1000,
    'peak_mb' => memory_get_peak_usage(true) / 1048576,
    'profile' => PSFS\base\types\helpers\GeneratorProfile::report(),
    'files' => count(moduleFiles($moduleDir)),
    'tree' => sha1(json_encode(moduleFiles($moduleDir))),
    'migrations' => count(glob($moduleDir . '/Config/Migrations/*') ?: []),
    'sync_marker' => count(glob(CACHE_DIR . '/generator-sync-*.json') ?: []),
]), PHP_EOL;
PHP;
}
