<?php

/**
 * PSFS Bootstrap (v2) - Multi-environment aware
 *
 * Responsibilities:
 *  - Detect framework mode: vendor / standalone / phar.
 *  - Define base directory constants accordingly.
 *  - Load Composer autoload (from project or from standalone mode).
 *  - Load global helper functions.
 */

// -----------------------------------------------------------------------------
// 1. Runtime metrics (optional)
// -----------------------------------------------------------------------------

use Symfony\Component\Finder\Finder;

defined('PSFS_START_TS') or define('PSFS_START_TS', microtime(true));
defined('PSFS_START_MEM') or define('PSFS_START_MEM', memory_get_usage(true));

// -----------------------------------------------------------------------------
// 2. Detect execution environment
// -----------------------------------------------------------------------------

$bootstrapDir = __DIR__;
$levels = 2;
if(false !== stripos($bootstrapDir, 'vendor')) {
    $levels = 4;
}// .../psfs/core/src
$vendorDir = dirname($bootstrapDir, $levels) . '/vendor';
$projectRoot = dirname($bootstrapDir, $levels); // When running as vendor

// Standalone mode: PSFS cloned and executed directly (no vendor/)
$standaloneRoot = dirname($bootstrapDir, $levels - 1); // .../psfs-core

// Detect vendor mode (framework inside vendor/)
$runningAsVendor = file_exists($vendorDir . '/autoload.php');

// Detect standalone mode
$runningStandalone = !$runningAsVendor;

// -----------------------------------------------------------------------------
// 3. Define base directory constants
// -----------------------------------------------------------------------------

defined('SOURCE_DIR') or define('SOURCE_DIR', $bootstrapDir);
if ($runningAsVendor) {
    // PSFS is being executed as a dependency in another project
    defined('BASE_DIR') or define('BASE_DIR', dirname($vendorDir, 1));   // The root of the host project
    defined('CORE_DIR') or define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'src');
    defined('PSFS_AS_VENDOR') or define('PSFS_AS_VENDOR', true);
} else {
    // Standalone development mode
    defined('BASE_DIR') or define('BASE_DIR', $standaloneRoot);
    defined('CORE_DIR') or define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'modules');
    defined('PSFS_AS_VENDOR') or define('PSFS_AS_VENDOR', false);
}
defined('VENDOR_DIR') or define('VENDOR_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'vendor');
defined('LOG_DIR') or define('LOG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
defined('CACHE_DIR') or define('CACHE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'cache');
defined('CONFIG_DIR') or define('CONFIG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'config');
defined('WEB_DIR') or define('WEB_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'html');
defined('LOCALE_DIR') or define('LOCALE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'locale');

// -----------------------------------------------------------------------------
// 4. Load Composer autoload
// -----------------------------------------------------------------------------

if ($runningAsVendor) {
    // Autoload from the host project's vendor directory
    require_once $vendorDir . '/autoload.php';
} else {
    // Standalone mode: PSFS itself is the project root
    $standaloneAutoload = BASE_DIR . '/vendor/autoload.php';
    if (!file_exists($standaloneAutoload)) {
        throw new RuntimeException(
            "Composer autoload not found. Run 'composer install' in the PSFS root directory."
        );
    }
    require_once $standaloneAutoload;
}

// -----------------------------------------------------------------------------
// 5. Load modules autoloader
// -----------------------------------------------------------------------------
if(file_exists(CORE_DIR)) {
    $finder = new Finder();
    $files = $finder->files()->in(CORE_DIR)->name('autoload.php');
    foreach ($files as $file) {
        require_once $file->getRealPath();
    }
}


// -----------------------------------------------------------------------------
// 6. Global helper functions
// -----------------------------------------------------------------------------
require_once SOURCE_DIR . '/functions.php';


// -----------------------------------------------------------------------------
// Bootstrap completed
// -----------------------------------------------------------------------------