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

if (!function_exists('debug')) {
    function debug($var, $varName = "", $die = FALSE)
    {
        $html = '<style>*{margin: 0} body{background: rgb(36,36,36); padding: 5px;}</style>';
        $html .= '<pre style="padding: 10px; margin: 5px; display: block; background: rgb(41,41,41); color: white; border-radius: 5px;">';
        if(is_null($var)) {
            if($varName) $html .= $varName . ' ==> <b>NULL</b>';
        } else {
            $html .= print_r('(' . gettype($var) . ') ', TRUE);
            if($varName) $html .= $varName . ' ==> ';
            if("boolean" === gettype($var)) {
                $html .= print_r($var ? "TRUE" : "FALSE", TRUE);
            } else if((is_array($var) && !empty($var)) || (!is_array($var)) ||  ($var === 0)) {
                $html .= print_r($var, TRUE);
            } else {
                $html .= 'empty';
            }
        }
        $html .= '</pre>';
        ob_start();
        echo $html;
        ob_flush();
        ob_end_clean();
        if ($die || $var === "die") {
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
