<?php

use PSFS\base\Logger;

require_once 'bootstrap.php';
/**
 * Simple, Fast & Secure Framework
 * @author Fran Lopez <fran.lopez84@hotmail.es>
 * @version 1.0
 */
defined("BASE_DIR") or define("BASE_DIR", dirname(__DIR__, preg_match('/vendor/', __DIR__) ? 4 : 1));
\PSFS\bootstrap::load();
if (!function_exists("PSFSAutoloader")) {
    // autoloader
    function PSFSAutoloader($class): false
    {
        // it only autoload class into the Rain scope
        if (str_contains($class, 'PSFS')) {
            // Change order src
            $class = preg_replace('/^\\\\?PSFS/', '', $class);
            // transform the namespace in path
            $path = str_replace("\\", DIRECTORY_SEPARATOR, $class);
            // filepath
            $abs_path = SOURCE_DIR . DIRECTORY_SEPARATOR . $path . ".php";
            // require the file
            if (file_exists($abs_path)) {
                require_once $abs_path;
            } else {
                Logger::log("{$class} not loaded with " . __FILE__);
            }
        }
        return false;
    }

    // register the autoloader
    spl_autoload_register("PSFSAutoloader");
}
