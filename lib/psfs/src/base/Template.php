<?php

namespace PSFS\Base;

use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\Dispatcher;

class Template extends Singleton{

    protected $tpl;

    private $debug = false;

    function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: false;
        $loader = new \Twig_Loader_Filesystem(Config::getInstance()->getTemplatePath());
        $this->tpl = new \Twig_Environment($loader, array(
            'cache' => Config::getInstance()->getCachePath(),
            'debug' => $this->debug,
        ));
    }

    /**
     * Método que procesa la plantilla
     * @param $tpl
     * @param array $vars
     * @return mixed
     */
    public function render($tpl, array $vars = array())
    {
        ob_start();
        if($this->debug)
        {
            header('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            header('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            $vars["__DEBUG__"]["includes"] = get_included_files();
            $vars["__DEBUG__"]["stacktrace"] = debug_backtrace();
        }
        echo $this->dump($tpl, $vars);
        ob_flush();
        ob_end_clean();
        exit();
    }

    /**
     * Método que devuelve el contenido de una plantilla
     * @param $tpl
     * @param array $vars
     * @return string
     */
    public function dump($tpl, array $vars = array())
    {
        return $this->tpl->render($tpl, $vars);
    }
}