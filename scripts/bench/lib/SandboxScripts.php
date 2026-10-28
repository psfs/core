<?php

declare(strict_types=1);

namespace Psfs\Bench;

/**
 * Scripts that run inside the sandbox. They are kept as text because they are copied there and executed by a
 * different PHP process.
 */
final class SandboxScripts
{
    /** Runs inside the server for every request and records memory, loaded files, PHP time and opcache state */
    public const PREPEND = <<<'PHP'
<?php
register_shutdown_function(static function () {
    $file = getenv('BENCH_STATS_FILE');
    if (!$file) {
        return;
    }
    $opcache = function_exists('opcache_get_status') && ($status = @opcache_get_status(false)) && !empty($status['opcache_enabled']);
    file_put_contents($file, sprintf(
        "%d\t%d\t%.3f\t%d\n",
        memory_get_peak_usage(false),
        count(get_included_files()),
        (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000,
        $opcache ? 1 : 0
    ), FILE_APPEND | LOCK_EX);
});
PHP;

    /** Runs once inside the sandbox: generates the benchmark modules (and the API module and database) */
    public const PREP = <<<'PHP'
<?php
define('PSFS_UNIT_TESTING_EXECUTION', true);
chdir(__DIR__);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/bootstrap.php';

[$script, $page, $api, $dummies] = $argv + [null, 0, 0, 0];
$generator = PSFS\services\GeneratorService::getInstance();

/** Until an admin exists the Dispatcher forces the installer page for every request: create a minimal one */
PSFS\base\Security::save([
    'username' => 'bench',
    'password' => bin2hex(random_bytes(8)),
    'profile' => PSFS\base\types\helpers\AuthHelper::USER_ID_TOKEN,
]);

/** A generated module is private to admins: make it public and give the page a known marker */
function publicize(string $module): void {
    $base = __DIR__ . '/modules/' . $module . '/Controller/base/' . $module . 'BaseController.php';
    $code = file_get_contents($base);
    $code = str_replace(['use PSFS\base\types\AuthAdminController;', 'extends AuthAdminController'], ['use PSFS\base\types\Controller;', 'extends Controller'], $code);
    file_put_contents($base, $code);
    $template = __DIR__ . '/modules/' . $module . '/Templates/index.html.twig';
    file_put_contents($template, str_replace('It works!', 'BENCH-PAGE-OK', file_get_contents($template)));
}

/** Only the API module may own the default datasource, otherwise the last loaded module wins */
function dropDefaultDatasource(string $module): void {
    $config = __DIR__ . '/modules/' . $module . '/Config/config.php';
    file_put_contents($config, preg_replace('/^.*setDefaultDatasource.*\R/m', '', file_get_contents($config)));
}

$db = null;
if ((int)$api) {
    require __DIR__ . '/tests_api/support/ClientModuleHarness.php';
    PSFS\apitests\support\ClientModuleHarness::acquire();
    $db = PSFS\base\config\Config::getParam('db.name');
}
if ((int)$page) {
    $generator->createStructureModule('BENCHPAGE', true, skipMigration: true);
    publicize('BENCHPAGE');
    dropDefaultDatasource('BENCHPAGE');
}
for ($i = 1; $i <= (int)$dummies; $i++) {
    $name = sprintf('BENCHDUMMY%02d', $i);
    $generator->createStructureModule($name, true, skipMigration: true);
    dropDefaultDatasource($name);
}
echo json_encode(['db' => $db]), PHP_EOL;
PHP;
}
