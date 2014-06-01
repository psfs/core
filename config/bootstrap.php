<?php
/**
 * Bootstrap general
 */
if(!defined("BASE_DIR")) define("BASE_DIR", realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR) );
if(!defined("LOG_DIR")) define("LOG_DIR", BASE_DIR.DIRECTORY_SEPARATOR.'logs');
if(!defined("CACHE_DIR")) define("CACHE_DIR", BASE_DIR.DIRECTORY_SEPARATOR.'cache');
if(!defined("CONFIG_DIR")) define("CONFIG_DIR", BASE_DIR.DIRECTORY_SEPARATOR.'config');
if(!defined("LIB_DIR")) define("LIB_DIR", BASE_DIR.DIRECTORY_SEPARATOR.'lib');

//Cargamos en memoria la función de desarrollo PRE
if(!function_exists("pre")){
    function pre($var, $die = false)
    {
        $html = "<pre style='padding:10px;margin:0;display:block;background: #EEE; box-shadow: inset 0 0 3px 3px #DDD; color: #666; text-shadow: 1px 1px 1px #CCC;border-radius: 5px;'>";
        $html .= (is_null($var)) ? "<b>NULL</b>" : print_r($var,true);
        $html .= "</pre>";
        ob_start();
        echo $html;
        ob_flush();
        ob_end_clean();
        if($die) die;
    }
}

$lib_path = realpath(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."lib".DIRECTORY_SEPARATOR);
$src_path = realpath(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."modules".DIRECTORY_SEPARATOR);
set_include_path(get_include_path().PATH_SEPARATOR.$lib_path.PATH_SEPARATOR.$src_path);

//Autoload de librerías
$d = dir($lib_path);
while(false !== ($dir = $d->read()))
{
    $file = str_replace(".php","",$dir);
    if(!is_dir($lib_path.$dir) && file_exists($lib_path.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR."autoload.php"))
    {
        include_once($lib_path.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR."autoload.php");
    }
}

//Autoload de módulos
$d = dir($src_path);
while(!empty($d) && false !== ($dir = $d->read()))
{
    $file = str_replace(".php","",$dir);
    if(!is_dir($src_path.$dir) && file_exists($src_path.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR."autoload.php"))
    {
        include_once($src_path.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR."autoload.php");
    }
}