<?php

namespace PSFS\base;

use PSFS\base\Singleton;
use PSFS\config\Config;

/**
 * Class Router
 * @package PSFS
 */
class Router extends Singleton{

    protected $routing;

    function __construct()
    {
        if(Config::getInstance()->getDebugMode() || !file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json"))
        {
            $this->hydrateRouting();
        }else $this->routing = file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . "urls.json");
    }

    /**
     * Método que deriva un error HTTP de página no encontrada
     */
    public function httpNotFound()
    {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    /**
     * Método que calcula el objeto a enrutar
     * @param $route
     *
     * @return bool
     */
    public function execute($route)
    {
        foreach($this->routing as $pattern => $action)
        {
            if(preg_match("/".preg_quote($pattern, "/")."$/i", $route))
            {
                return call_user_func_array(array($action["class"], $action["method"]), array());
            }
        }

        return false;
    }

    /**
     * Método que devuelve el namespace asociado a una clase
     * @param $class
     *
     * @return null
     */
    protected function findClass($class)
    {
        //Buscamos en PSFS en primera instancia
        $class_namespace = $this->exploreDir(LIB_DIR . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'src', $class);
        //Si no tenemos la ruta buscamos en la carpeta de módulos
        if(is_null($class_namespace))
        {
            $class_namespace = $this->exploreDir(BASE_DIR . DIRECTORY_SEPARATOR . 'modules', $class, 'Modules');
        }
        return $class_namespace;
    }

    /**
     * Método que busca en la estructura de directorios la clase
     * @param $dir
     * @param $class
     * @param $base
     * @return null
     */
    protected function exploreDir($orig_dir, $class, $base = 'PSFS')
    {
        $class_namespace = null;
        if(file_exists($orig_dir. DIRECTORY_SEPARATOR . $class . ".php"))
        {
            $class_namespace = $base . "\\" . $class;
        }elseif(is_dir($orig_dir))
        {
            $d = dir($orig_dir);
            while(false !== ($dir = $d->read()))
            {
                if(is_dir($orig_dir.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
                {
                    $class_namespace = $this->exploreDir($orig_dir.DIRECTORY_SEPARATOR.$dir, $class, $base . "\\" . $dir);
                    if(!is_null($class_namespace)) break;
                }
            }
        }
        return $class_namespace;
    }

    /**
     * Método que regenera el fichero de rutas
     */
    private function hydrateRouting()
    {
        $base = LIB_DIR . DIRECTORY_SEPARATOR . 'psfs' . DIRECTORY_SEPARATOR . 'src';
        $modules = BASE_DIR . DIRECTORY_SEPARATOR . 'modules';
        $this->routing = $this->inspectDir($base, "PSFS", array());
        $this->routing = $this->inspectDir($modules, "", $this->routing);
    }

    /**
     * Método que inspecciona los directorios en busca de clases que registren rutas
     * @param $dir
     * @param $routing
     *
     * @return mixed
     */
    private function inspectDir($origen, $namespace = "PSFS", $routing)
    {
        $d = dir($origen);
        while(false !== ($dir = $d->read()))
        {
            if(is_dir($origen.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
            {
                $routing = $this->inspectDir($origen.DIRECTORY_SEPARATOR.$dir, $namespace . '\\' . $dir, $routing);
            }elseif(preg_match("/\.php$/",$dir)){
                $routing = $this->addRouting($namespace . '\\' .str_replace(".php", "", $dir), $routing);
            }
        }
        return $routing;
    }

    /**
     * Método que añade nuevas rutas al array de referencia
     * @param $routing
     *
     * @return mixed
     */
    private function addRouting($namespace, $routing)
    {
        if(class_exists($namespace))
        {
            $reflection = new \ReflectionClass($namespace);
            if(false === $reflection->isAbstract() && false === $reflection->isInterface())
            {
                foreach($reflection->getMethods() as $method)
                {
                    if($method->isPublic())
                    {
                        preg_match("/@route\ (.*)\n/i", $method->getDocComment(), $sr);
                        if(count($sr))
                        {
                            $regex = $sr[1] ?: $sr[0];
                            $routing[$regex] = array(
                                "class" => $namespace,
                                "method" => $method->getName(),
                                "params" => $method->getParameters(),
                            );
                        }
                    }
                }
            }
        }
        return $routing;
    }
}