<?php
if (defined('PSFS_BOOTSTRAP_LOADED')) return;
use Symfony\Component\Finder\Finder;

if (!defined('SOURCE_DIR')) define('SOURCE_DIR', __DIR__);
if (preg_match('/vendor/', SOURCE_DIR)) {
    if (!defined('BASE_DIR')) define('BASE_DIR', SOURCE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..');
    if (!defined('CORE_DIR')) define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'src');
} else {
    if (!defined('BASE_DIR')) define('BASE_DIR', SOURCE_DIR . DIRECTORY_SEPARATOR . '..');
    if (!defined('CORE_DIR')) define('CORE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'modules');
}
if (!defined('LOG_DIR')) define('LOG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'logs');
if (!defined('CACHE_DIR')) define('CACHE_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'cache');
if (!defined('CONFIG_DIR')) define('CONFIG_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'config');
if (!defined('WEB_DIR')) define('WEB_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'html');

//Cargamos en memoria la función de desarrollo PRE
if (!function_exists('pre')) {
    function pre($var, $die = FALSE)
    {
        $html = '<pre style="padding:10px;margin:0;display:block;background: #EEE; box-shadow: inset 0 0 3px 3px #DDD; color: #666; text-shadow: 1px 1px 1px #CCC;border-radius: 5px;">';
        $html .= (is_null($var)) ? '<b>NULL</b>' : print_r($var, TRUE);
        $html .= '</pre>';
        if(class_exists('\\PSFS\\Dispatcher')) {
            $html .= '<pre>['.round(\PSFS\Dispatcher::getInstance()->getMem('MBytes'), 3).'Mb - '.round(\PSFS\Dispatcher::getInstance()->getTs(), 3).'s]</pre>';
        }
        ob_start();
        echo $html;
        ob_flush();
        ob_end_clean();
        if ($die) {
            if(class_exists('\\PSFS\\base\\Logger')) {
                \PSFS\base\Logger::log('Execution finished via pre', LOG_WARNING);
            }
            die;
        }
    }
}

if (!function_exists('jpre')) {
    function jpre($var, $die = false)
    {
        echo json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($die) {
            die;
        }
    }
}

if (file_exists(CORE_DIR)) {
    //Autoload de módulos
    $finder = new Finder();
    $finder->files()->in(CORE_DIR)->name('autoload.php');
    /* @var $file SplFileInfo */
    foreach ($finder as $file) {
        $path = $file->getRealPath();
        include_once($path);
    }
}

\PSFS\base\Logger::log('Bootstrap initialized');

defined('PSFS_BOOTSTRAP_LOADED') or define('PSFS_BOOTSTRAP_LOADED', true);