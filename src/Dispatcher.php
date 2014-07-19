<?php

namespace PSFS;

use PSFS\base\Forms;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\exception\ConfigException;
use PSFS\exception\LoggerException;

/**
 * Class Dispatcher
 * @package PSFS
 */
class Dispatcher extends Singleton{
    private $router;
    private $parser;
    private $security;
    private $log;
    private $config;

    protected $ts;
    protected $mem;
    protected $locale = "es_ES";

    /**
     * Constructor por defecto
     * @param $mem
     */
    public function __construct($mem = 0){
        $this->router = Router::getInstance();
        $this->parser = Request::getInstance();
        $this->security = Security::getInstance();
        $this->log = Logger::getInstance();
        $this->ts = $this->parser->getTs();
        $this->mem = memory_get_usage();
        $this->config = Config::getInstance();
        $this->setLocale();
    }

    private function setLocale()
    {
        //Cargamos traducciones
        putenv("LC_ALL=" . $this->locale);
        setlocale(LC_ALL, $this->locale);
        //Cargamos el path de las traducciones
        $locale_path = __DIR__ . DIRECTORY_SEPARATOR . 'locale';
        if(!file_exists($locale_path)) @mkdir($locale_path);
        bindtextdomain('psfs', $locale_path);
        textdomain('psfs');
        bind_textdomain_codeset('psfs', 'UTF-8');
        return $this;
    }

    /**
     * Método inicial
     */
    public function run()
    {
        $this->log->infoLog("Inicio petición ".$this->parser->getrequestUri());
        if(!$this->config->isConfigured()) return $this->config->config();
        //
        try{
            if(!$this->parser->isFile())
            {
                if(Config::getInstance()->getDebugMode())
                {
                    //Warning & Notice handler
                    set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext){
                        throw new \ErrorException($errstr, 500, $errno, $errfile, $errline);
                    }, E_WARNING);
                    set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext){
                        throw new \ErrorException($errstr, 500, $errno, $errfile, $errline);
                    }, E_NOTICE);
                }
                if(!$this->router->execute($this->parser->getServer("REQUEST_URI"))) return $this->router->httpNotFound();
            }else $this->router->httpNotFound();
        }catch(ConfigException $ce)
        {
            return $this->config->config();
        }
        catch(\Exception $e)
        {
            $this->log->errorLog($e);
            return $this->router->httpNotFound($e);
        }
    }

    /**
     * Método que devuelve la memoria usada desde la ejecución
     * @param $formatted
     *
     * @return int
     */
    public function getMem($unit = "Bytes")
    {
        $use = memory_get_usage() - $this->mem;
        switch($unit)
        {
            case "KBytes": $use /= 1024; break;
            case "MBytes": $use /= (1024*1024); break;
            case "Bytes":
            default:
        }
        return $use;
    }

    /**
     * Método que devuelve el tiempo pasado desde el inicio del script
     * @return mixed
     */
    public function getTs()
    {
        return microtime(true) - $this->ts;
    }

    /**
     * Método que recorre los directorios para extraer las traducciones posibles
     * @route /admin/translations
     */
    public function getTranslations($locale = 'es_ES')
    {
        $locale_path = realpath(SOURCE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = self::findTranslations(SOURCE_DIR, $locale);
        echo "<hr>";
        echo _('Compilando traducciones');
        $result = shell_exec("msgfmt {$locale_path}psfs.po -o {$locale_path}psfs.mo");
        pre($result);
        echo "Fin";
        exit();
    }

    /**
     * Método que revisa las traducciones directorio a directorio
     * @param $path
     * @param $locale
     */
    private static function findTranslations($path, $locale)
    {
        $locale_path = realpath(SOURCE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = false;
        $d = dir($path);
        while(false !== ($dir = $d->read()))
        {
            $join = (file_exists($locale_path . 'psfs.po')) ? '-j' : '';
            $cmd = "xgettext --from-code=UTF-8 {$join} -o {$locale_path}psfs.po ".$path.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR."*.php";
            if(is_dir($path.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
            {
                echo "<li>" . _('Revisando directorio: ') . $path.DIRECTORY_SEPARATOR.$dir;
                echo "<li>" . _('Comando ejecutado: '). $cmd;
                $return = shell_exec($cmd);
                echo "<li>" . _('Con salida:') . '<pre>' . $return . '</pre>';
                $translations = self::findTranslations($path.DIRECTORY_SEPARATOR.$dir, $locale);
            }
        }
        return $translations;
    }
}