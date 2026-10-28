<?php
/**
 * Regeneration profile: how long a module with N tables takes to regenerate and where the time and memory go.
 * Run it through scripts/bench/regenerate.sh (it executes inside the php container).
 *
 *   --tables=10,50,100   tables of the module (default 10,50)
 *   --states=initial,regen,repeat   initial: no tables in the database yet; regen: database already up to date;
 *                                   repeat: regen again with generator.migration.skip_unchanged enabled
 */
declare(strict_types=1);

use Psfs\Bench\Regeneration;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Psfs\\Bench\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__ . '/lib/' . substr($class, strlen($prefix)) . '.php';
    }
});

$tables = ['10', '50'];
$states = Regeneration::STATES;
foreach (array_slice($argv, 1) as $argument) {
    [$key, $value] = array_pad(explode('=', ltrim($argument, '-'), 2), 2, '');
    match ($key) {
        'tables' => $tables = explode(',', $value),
        'states' => $states = explode(',', $value),
        default => throw new InvalidArgumentException('Unknown option --' . $key),
    };
}
echo Regeneration::report((new Regeneration(array_map('intval', $tables), $states, dirname(__DIR__, 2)))->run());
