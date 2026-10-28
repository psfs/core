<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * The sandbox, database and rows that the runtime comparison serves. It is built where the other containers
 * (nginx, the load generator) can read it, because they mount the project directory.
 */
final class RuntimeFixture
{
    private const ROWS = 1000;
    /** Reports the settings a runtime really runs with, so the comparison can say what it compared */
    private const INFO_SCRIPT = <<<'PHP'
<?php
header('Content-Type: application/json');
$status = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
echo json_encode([
    'sapi' => PHP_SAPI,
    'opcache' => is_array($status) && !empty($status['opcache_enabled']),
    'jit' => is_array($status) && !empty($status['jit']['enabled']),
    'session' => ini_get('session.save_handler'),
    'memory_limit' => ini_get('memory_limit'),
]);
PHP;

    /**
     * @return array{db: string}
     */
    public static function prepare(string $path): array
    {
        $options = Options::fromArguments(['--scenarios=page,api-get', '--modules=2']);
        $services = Services::connect($options);
        $sandbox = new Sandbox(dirname(__DIR__, 3), $options, $path);
        $sandbox->build();
        $sandbox->useModules(2);
        (new Dataset($services->pdo(), $sandbox->database()))->useRows(self::ROWS);
        $sandbox->writeConfig(['debug' => false, 'psfs.redis' => false]);
        $sandbox->resetCaches();
        file_put_contents($path . '/html/bench-info.php', self::INFO_SCRIPT);
        // php-fpm workers run as www-data and the CLI servers as root: every runtime must be able to write the caches
        Shell::run('chmod -R a+rwX ' . escapeshellarg($path));
        return ['db' => $sandbox->database()];
    }

    public static function clean(string $path, string $database): void
    {
        // the API scenario is what makes the services connect to MySQL
        $options = Options::fromArguments(['--scenarios=page,api-get', '--modules=2']);
        $services = Services::connect($options);
        if ('' !== $database) {
            $services->dropDatabase($database);
        }
        $services->removeCreatedKeys();
        (new Sandbox(dirname(__DIR__, 3), $options, $path))->remove();
    }
}
