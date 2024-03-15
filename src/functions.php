<?php

use PSFS\base\extension\CustomTranslateExtension;
use Symfony\Component\Finder\Finder;

//Cargamos en memoria la función de desarrollo PRE
if (!function_exists('pre')) {
    /**
     * @param mixed $var
     * @param boolean $die
     * @return void
     */
    function pre(mixed $var, bool $die = false): void
    {
        $html = '<pre style="padding:10px;margin:0;display:block;background: #EEE; box-shadow: inset 0 0 3px 3px #DDD; color: #666; text-shadow: 1px 1px 1px #CCC;border-radius: 5px;">';
        $html .= is_null($var) ? '<b>NULL</b>' : print_r($var, TRUE);
        $html .= '</pre>';
        echo $html;
        if ($die) {
            die;
        }
    }
}

if (!function_exists("getallheaders")) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $h => $v) {
            if (preg_match('/HTTP_(.+)/', $h, $hp)) {
                $headers[$hp[1]] = $v;
            }
        }
        return $headers;
    }
}

if (file_exists(CORE_DIR)) {
    $loaded_files = [];
    //Autoload de módulos
    $finder = new Finder();
    $finder->files()->in(CORE_DIR)->name('autoload.php');
    /* @var $file SplFileInfo */
    foreach ($finder as $file) {
        $path = $file->getRealPath();
        if (!in_array($path, $loaded_files)) {
            $loaded_files[] = $path;
            require_once($path);
        }
    }
}

if (!function_exists('t')) {
    function t($message, $key = null, $reload = false)
    {
        return CustomTranslateExtension::_($message, $key, $reload);
    }
}
