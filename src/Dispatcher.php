<?php

namespace PSFS;

use PSFS\base\Forms;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;
use PSFS\base\Singleton;
use PSFS\base\config\Config;
use PSFS\base\exception\ConfigException;
use PSFS\base\exception\LoggerException;
use PSFS\base\exception\SecurityException;
use PSFS\base\Template;

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
        $this->locale = $this->config->get("default_language");
        //Cargamos traducciones
        putenv("LC_ALL=" . $this->locale);
        setlocale(LC_ALL, $this->locale);
        //Cargamos el path de las traducciones
        $locale_path = BASE_DIR . DIRECTORY_SEPARATOR . 'locale';
        if(!file_exists($locale_path)) @mkdir($locale_path);
        bindtextdomain('translations', $locale_path);
        textdomain('translations');
        bind_textdomain_codeset('translations', 'UTF-8');
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
                if($this->config->getDebugMode())
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
        }
        catch(\Exception $e)
        {
            $error = array(
                "error" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            );
            $this->log->errorLog($error);
            unset($error);
            if($e instanceof ConfigException) return $this->config->config();
            if($e instanceof SecurityException) return $this->security->notAuthorized($this->parser->getServer("REQUEST_URI"));
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
     * @route /admin/translations/{locale}
     */
    public function getTranslations($locale = 'en_GB')
    {
        //Generamos las traducciones de las plantillas
        Template::getInstance()->regenerateTemplates();

        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        //Localizamos xgettext
        $translations = self::findTranslations(SOURCE_DIR, $locale);
        $translations = self::findTranslations(CORE_DIR, $locale);
        $translations = self::findTranslations(CACHE_DIR, $locale);
        echo "<hr>";
        echo _('Compilando traducciones');
        pre("msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo");
        exec("export PATH=\$PATH:/opt/local/bin:/bin:/sbin; msgfmt {$locale_path}translations.po -o {$locale_path}translations.mo", $result);
        echo "Fin";
        pre($result);
        exit();
    }

    /**
     * Método que revisa las traducciones directorio a directorio
     * @param $path
     * @param $locale
     */
    private static function findTranslations($path, $locale)
    {
        $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = false;
        $d = dir($path);
        while(false !== ($dir = $d->read()))
        {
            if(!file_exists($locale_path)) mkdir($locale_path, 0777, true);
            if(!file_exists($locale_path . 'translations.po')) file_put_contents($locale_path . 'translations.po', '');
            $inspect_path = realpath($path.DIRECTORY_SEPARATOR.$dir);
            $cmd_php = "export PATH=\$PATH:/opt/local/bin; xgettext ". $inspect_path . DIRECTORY_SEPARATOR ."*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$locale_path}translations.po";
            if(is_dir($path.DIRECTORY_SEPARATOR.$dir) && preg_match('/^\./',$dir) == 0)
            {
                $return = array();
                echo "<li>" . _('Revisando directorio: ') . $inspect_path;
                echo "<li>" . _('Comando ejecutado: '). $cmd_php;
                shell_exec($cmd_php);// . " >> " . __DIR__ . DIRECTORY_SEPARATOR . "debug.log 2>&1");
                usleep(10);
                $translations = self::findTranslations($inspect_path, $locale);
            }
        }
        return $translations;
    }
}