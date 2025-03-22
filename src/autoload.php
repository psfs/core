<?php

declare(strict_types=1);

namespace PSFS;


require_once 'bootstrap.php';

defined("BASE_DIR") or define("BASE_DIR", dirname(__DIR__, preg_match('/vendor/', __DIR__) ? 4 : 1));
bootstrap::load();

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        if (str_starts_with($class, 'PSFS\\')) {
            $relativeClass = substr($class, strlen('PSFS\\'));

            $file = SOURCE_DIR . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            } else if (class_exists('PSFS\\base\\Logger')) {
                \PSFS\base\Logger::log("[Autoloader] Class $class not found at $file", LOG_WARNING);
            }
        }
    }
}

// Registro automático del autoloader (puedes comentarlo si lo haces desde bootstrap)
if (!defined('SOURCE_DIR')) {
    define('SOURCE_DIR', dirname(__DIR__) . '/src');
}

Autoloader::register();
