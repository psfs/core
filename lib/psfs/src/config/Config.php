<?php

namespace PSFS\config;

use PSFS\base\Singleton;
use PSFS\exception\ConfigException;
use PSFS\config\ConfigForm;
use PSFS\base\Logger;
use PSFS\base\Template;
use PSFS\base\Request;

/**
 * Class Config
 * @package PSFS\config
 */
class Config extends Singleton{

    const DEFAULT_LANGUAGE = 'es';
    const DEFAULT_ENCODE = 'UTF-8';
    const DEFAULT_CTYPE = 'text/html';
    const DEFAULT_DATETIMEZONE = 'Europe/Madrid';

    protected $config;
    static public $required = array('db_host', 'db_port', 'db_user', 'db_password', 'home_action');

    static public $optional = array('platform_name',);
    protected $debug = false;

    function __construct()
    {
        $this->configure();
    }

    /**
     * Método que carga la configuración del sistema
     * @param null $path
     *
     * @return mixed
     */
    protected function configure()
    {
        if(file_exists(CONFIG_DIR . '/config.json'))
        {
            $this->config = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'config.json'), true) ?: array();
            $this->debug = (bool)$this->config['debug'] ?: false;
        }else{
            $this->debug = true;
        }
        return $this;
    }

    /**
     * Método que devuelve si la plataforma está en modo debug
     * @return bool
     */
    public function getDebugMode(){ return $this->debug; }

    /**
     * Método que devuelve el path de la carpeta lib
     * @return string
     */
    public function getLibPath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR); }

    /**
     * Método que devuelve el path de cache
     * @return string
     */
    public function getCachePath(){ return CACHE_DIR; }

    /**
     * Método que devuelve el path general de templates de PSFS
     * @return string
     */
    public function getTemplatePath(){ return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR ); }

    /**
     * Método que indica si se ha configurado correctamente la plataforma
     * @return bool
     */
    public function isConfigured()
    {
        $configured = true;
        foreach(static::$required as $required)
        {
            if(empty($this->config[$required]))
            {
                $configured = false;
                break;
            }
        }
        return $configured;
    }

    /**
     * Método que guarda la configuración del framework
     * @param array $data
     *
     * @return bool
     */
    public static function save(array $data, array $extra = null)
    {
        //En caso de tener parámetros nuevos los guardamos
        if(!empty($extra["label"])) foreach($extra["label"] as $index => $field)
        {
            if(isset($extra["value"][$index]) && !empty($extra["value"][$index])) /** @var $data array */
                $data[$field] = $extra["value"][$index];
        }
        $final_data = array();
        if(!empty($data)) foreach($data as $key => $value)
        {
            if(!empty($value)) $final_data[$key] = $value;
        }
        return (false !== file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . 'config.json', json_encode($final_data)));
    }

    /**
     * Método que devuelve un parámetro de configuración
     * @param $param
     *
     * @return null
     */
    public function get($param)
    {
        return $this->config[$param] ?: null;
    }

    /**
     * Método que devuelve toda la configuración en un array
     * @return mixed
     */
    public function dumpConfig()
    {
        return $this->config;
    }

    /**
     * Método que gestiona la configuración de las variables
     * @Route /admin/config
     * @return mixed
     * @throws \HttpException
     */
    public function config(){
        Logger::getInstance()->infoLog("Arranque del Config Loader al solicitar ".Request::getInstance()->getrequestUri());
        /* @var $form \PSFS\config\ConfigForm */
        $form = new ConfigForm;
        $form->build();
        if(Request::getInstance()->getMethod() == 'POST')
        {
            $form->hydrate();
            if($form->isValid())
            {
                if(self::save($form->getData(), $form->getExtraData()))
                {
                    Logger::getInstance()->infoLog("Configuración guardada correctamente");
                    return Request::getInstance()->redirect();
                }
                throw new \HttpException('Error al guardar la configuración, prueba a cambiar los permisos', 403);
            }
        }
        return Template::getInstance()->render('welcome.html.twig', array(
            'text' => _("Bienvenido a PSFS"),
            'config' => $form,
        ));
    }

    /**
     * Método que gestiona el menú de administración
     * @route /admin
     * @route /admin/
     * @return mixed
     */
    public function index(){
        return Template::getInstance()->render("index.html.twig", array(

        ));
    }
}