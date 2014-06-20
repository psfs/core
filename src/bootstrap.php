<?php
    /**
     * Bootstrap general
     */
    if (!defined("BASE_DIR")) define("BASE_DIR", __DIR__);
    if (!defined("SOURCE_DIR")) define("SOURCE_DIR", BASE_DIR);
    if (!defined("LOG_DIR")) define("LOG_DIR", BASE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs');
    if (!defined("CACHE_DIR")) define("CACHE_DIR", BASE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache');
    if (!defined("CONFIG_DIR")) define("CONFIG_DIR", BASE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config');
    if (!defined("LIB_DIR")) define("LIB_DIR", BASE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor');
    if (!defined("CORE_DIR")) define("CORE_DIR", BASE_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'modules');

//Cargamos en memoria la función de desarrollo PRE
    if (!function_exists("pre")) {
        function pre($var, $die = FALSE)
        {
            $html = "<pre style='padding:10px;margin:0;display:block;background: #EEE; box-shadow: inset 0 0 3px 3px #DDD; color: #666; text-shadow: 1px 1px 1px #CCC;border-radius: 5px;'>";
            $html .= (is_null($var)) ? "<b>NULL</b>" : print_r($var, TRUE);
            $html .= "</pre>";
            ob_start();
            echo $html;
            ob_flush();
            ob_end_clean();
            if ($die) die;
        }
    }

//Autoload de librerías
    require_once "autoload.php";
//require_once(LIB_DIR . DIRECTORY_SEPARATOR . "autoload.php");

//Autoload de módulos
    $d = dir(CORE_DIR);
    while (!empty($d) && FALSE !== ($dir = $d->read())) {
        $file = str_replace(".php", "", $dir);
        if (!is_dir($src_path . $dir) && file_exists($src_path . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . "autoload.php")) {
            include_once($src_path . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR . "autoload.php");
        }
    }