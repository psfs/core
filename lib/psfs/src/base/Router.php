<?php

namespace PSFS\base;

use PSFS\base\Singleton;

/**
 * Class Router
 * @package PSFS
 */
class Router extends Singleton{

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
        $class = $this->findClass(explode("/",$route)[1]);

        pre($class);
        return (!is_null($class));
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
            $class_namespace = $base . "/" . $class;
        }elseif(is_dir($orig_dir))
        {
            $d = dir($orig_dir);
            while(false !== ($dir = $d->read()))
            {
                if(is_dir($orig_dir.DIRECTORY_SEPARATOR.$dir) && preg_match("/^\./",$dir) == 0)
                {
                    $class_namespace = $this->exploreDir($orig_dir.DIRECTORY_SEPARATOR.$dir, $class, $base . "/" . $dir);
                    if(!is_null($class_namespace)) break;
                }
            }
        }
        return $class_namespace;
    }
}